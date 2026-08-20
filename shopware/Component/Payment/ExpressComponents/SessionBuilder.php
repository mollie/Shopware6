<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateSession;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SessionBuilder implements SessionBuilderInterface
{
    /**
     * Cart extension holding the session of the express components. Deliberately not
     * Mollie::EXTENSION, that key already carries the PayPal express session.
     */
    public const CART_EXTENSION = 'mollie_express_components';

    /**
     * Custom field of the order holding the sessions of the express components. An own key and not
     * a part of Mollie::EXTENSION, because savePaymentExtension() replaces that key completely
     * whenever a payment is written and would drop the sessions with it.
     */
    public const ORDER_CUSTOM_FIELD = 'mollie_express_components';
    public const ORDER_CUSTOM_FIELD_SESSION_ID = 'session_id';

    /**
     * A session only exists in the environment it was created in, so test and live sessions are
     * kept apart. Otherwise switching the mode leaves the cart or the order pointing at a session
     * the other environment does not know.
     */
    public static function cartExtensionKey(Mode $mode): string
    {
        return self::CART_EXTENSION . '_' . $mode->value;
    }

    /**
     * @param array<mixed> $customFields
     */
    public static function readOrderSessionId(array $customFields, Mode $mode): ?string
    {
        $sessionId = ($customFields[self::ORDER_CUSTOM_FIELD][$mode->value] ?? [])[self::ORDER_CUSTOM_FIELD_SESSION_ID] ?? null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    /**
     * Details Mollie collects from the shopper inside the express component. They are sent for a
     * logged in customer too: Mollie rejects a session that offers shippingOptions without asking
     * for a shipping address, and the shopper may well pick another address inside the wallet than
     * the one on the account.
     */
    private const REQUIRED_CUSTOMER_DETAILS = ['email', 'billing-address', 'shipping-address'];

    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: SessionLineBuilder::class)]
        private SessionLineBuilderInterface $lineBuilder,
        #[Autowire(service: ShippingOptionsResolver::class)]
        private ShippingOptionsResolverInterface $shippingOptionsResolver,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: CartPersister::class)]
        private AbstractCartPersister $cartPersister,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function buildFromCart(Cart $cart, SalesChannelContext $salesChannelContext): Session
    {
        $amount = new Money($this->getAmountWithoutShipping($cart), $salesChannelContext->getCurrency()->getIsoCode());
        $mode = $this->getMode($salesChannelContext);

        $existingSession = $this->loadExistingSession($cart, $amount, $mode, $salesChannelContext);
        if ($existingSession instanceof Session) {
            return $existingSession;
        }

        $session = $this->sessionGateway->createSession(
            $this->buildCreateSession($cart, $amount, $salesChannelContext),
            $salesChannelContext
        );

        $cart->addExtension(self::cartExtensionKey($mode), $session);
        $this->cartPersister->save($cart, $salesChannelContext);

        return $session;
    }

    /**
     * The edit order page has no cart, the session is built from the order instead and kept in its
     * custom fields. The shipping method of an order is already decided, so a single shipping
     * option is offered - Mollie requires at least one whenever the shopper may pick an address.
     */
    public function buildFromOrder(OrderEntity $order, SalesChannelContext $salesChannelContext): Session
    {
        $amount = new Money($this->getOrderAmountWithoutShipping($order), $salesChannelContext->getCurrency()->getIsoCode());
        $mode = $this->getMode($salesChannelContext);

        $existingSession = $this->loadExistingOrderSession($order, $amount, $mode, $salesChannelContext);
        if ($existingSession instanceof Session) {
            return $existingSession;
        }

        $createSession = new CreateSession(
            $this->buildDescription($salesChannelContext),
            $this->routeBuilder->getExpressComponentsOrderRedirectUrl($order->getId()),
            $amount
        );
        $createSession->setCancelUrl($this->routeBuilder->getExpressComponentsCancelUrl());
        $createSession->setLines($this->lineBuilder->buildFromOrder($order, $amount, $salesChannelContext));
        $createSession->setShippingOptions($this->buildOrderShippingOptions($order, $salesChannelContext));

        $this->applyCustomer($createSession, $salesChannelContext);

        $session = $this->sessionGateway->createSession($createSession, $salesChannelContext);

        $this->saveSessionOnOrder($order, $session->getId(), $mode, $salesChannelContext);

        return $session;
    }

    private function loadExistingOrderSession(OrderEntity $order, Money $amount, Mode $mode, SalesChannelContext $salesChannelContext): ?Session
    {
        $sessionId = self::readOrderSessionId($order->getCustomFields() ?? [], $mode);
        if ($sessionId === null) {
            return null;
        }

        try {
            $session = $this->sessionGateway->getSession($sessionId, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->warning('Stored express components order session could not be loaded, creating a new one', [
                'error' => $exception->getMessage(),
                'sessionId' => $sessionId,
                'orderId' => $order->getId(),
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            ]);

            return null;
        }

        // a completed session belongs to a payment that already happened, it must not be offered again
        if ($session->getStatus()->isCompleted() || ! $this->matchesAmount($session, $amount)) {
            return null;
        }

        return $session;
    }

    private function saveSessionOnOrder(OrderEntity $order, string $sessionId, Mode $mode, SalesChannelContext $salesChannelContext): void
    {
        $customFields = $order->getCustomFields() ?? [];
        $modeSessions = is_array($customFields[self::ORDER_CUSTOM_FIELD] ?? null) ? $customFields[self::ORDER_CUSTOM_FIELD] : [];
        $modeSessions[$mode->value] = [self::ORDER_CUSTOM_FIELD_SESSION_ID => $sessionId];
        $customFields[self::ORDER_CUSTOM_FIELD] = $modeSessions;

        $order->setCustomFields($customFields);

        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'customFields' => $customFields,
            ],
        ], $salesChannelContext->getContext());
    }

    /**
     * The delivery of the order already carries the shipping method the customer decided on, so
     * that one option is all Mollie gets. Recalculating alternatives is not possible here anyway:
     * the resolver prices them through the cart, and on the edit order page there is none.
     */
    private function buildOrderShippingOptions(OrderEntity $order, SalesChannelContext $salesChannelContext): ShippingOptionCollection
    {
        $shippingOptions = new ShippingOptionCollection();
        $currencyIso = $salesChannelContext->getCurrency()->getIsoCode();

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            if (! $shippingMethod instanceof ShippingMethodEntity) {
                continue;
            }

            $description = trim((string) ($shippingMethod->getTranslation('name') ?? $shippingMethod->getName()));
            $shippingOptions->add(new ShippingOption(
                $description !== '' ? $description : 'Shipping',
                $shippingMethod->getId(),
                new Money($delivery->getShippingCosts()->getTotalPrice(), $currencyIso)
            ));
        }

        return $shippingOptions;
    }

    private function getOrderAmountWithoutShipping(OrderEntity $order): float
    {
        $currency = $order->getCurrency();
        $total = $currency instanceof CurrencyEntity
            ? Money::fromOrder($order, $currency)->getValue()
            : $order->getAmountTotal();

        $shippingCosts = $order->getShippingCosts();
        $shipping = $shippingCosts->getTotalPrice();

        if ((string) $order->getTaxStatus() === CartPrice::TAX_STATE_NET) {
            $shipping += $shippingCosts->getCalculatedTaxes()->getAmount();
        }

        return $total - $shipping;
    }

    /**
     * A session cannot be edited after it was created, so it is only reused while it still
     * matches the cart. Any change to the total means a new session has to be created.
     */
    private function loadExistingSession(Cart $cart, Money $amount, Mode $mode, SalesChannelContext $salesChannelContext): ?Session
    {
        $storedSession = $cart->getExtension(self::cartExtensionKey($mode));
        if (! $storedSession instanceof Session) {
            return null;
        }

        try {
            $session = $this->sessionGateway->getSession($storedSession->getId(), $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->warning('Stored express components session could not be loaded, creating a new one', [
                'error' => $exception->getMessage(),
                'sessionId' => $storedSession->getId(),
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            ]);

            return null;
        }

        if (! $this->matchesAmount($session, $amount)) {
            $this->logger->debug('Express components session no longer matches the cart total, creating a new one', [
                'sessionId' => $session->getId(),
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            ]);

            return null;
        }

        return $session;
    }

    /**
     * The shipping costs are transported as shippingOptions and added by Mollie once the
     * shopper picks one, so neither the amount nor the lines may contain them.
     */
    private function getAmountWithoutShipping(Cart $cart): float
    {
        $shippingCosts = $cart->getDeliveries()->getShippingCosts()->sum();
        $shipping = $shippingCosts->getTotalPrice();

        // in a net cart the line prices are net while the cart total is gross
        if ($cart->getPrice()->getTaxStatus() === CartPrice::TAX_STATE_NET) {
            $shipping += $shippingCosts->getCalculatedTaxes()->getAmount();
        }

        return $cart->getPrice()->getTotalPrice() - $shipping;
    }

    private function getMode(SalesChannelContext $salesChannelContext): Mode
    {
        return $this->settings->getApiSettings($salesChannelContext->getSalesChannelId())->getMode();
    }

    private function matchesAmount(Session $session, Money $amount): bool
    {
        $sessionAmount = $session->getAmount();
        if (! $sessionAmount instanceof Money) {
            return false;
        }

        if ($sessionAmount->getCurrency() !== $amount->getCurrency()) {
            return false;
        }

        $decimals = $amount->getDecimals();

        return round($sessionAmount->getValue(), $decimals) === round($amount->getValue(), $decimals);
    }

    private function buildCreateSession(Cart $cart, Money $amount, SalesChannelContext $salesChannelContext): CreateSession
    {
        $createSession = new CreateSession(
            $this->buildDescription($salesChannelContext),
            $this->routeBuilder->getExpressComponentsRedirectUrl($cart->getToken()),
            $amount
        );
        $createSession->setCancelUrl($this->routeBuilder->getExpressComponentsCancelUrl());
        $createSession->setLines($this->lineBuilder->build($cart, $amount, $salesChannelContext));

        $this->applyCustomer($createSession, $salesChannelContext);
        $this->applyShippingOptions($createSession, $salesChannelContext);

        return $createSession;
    }

    /**
     * The options are built for the shipping country of the current context. Once the shopper
     * picks a different address inside the component, Mollie asks the callback url for the
     * options of that address.
     */
    private function applyShippingOptions(CreateSession $createSession, SalesChannelContext $salesChannelContext): void
    {
        // The callback url is not sent yet: sessions reject it with "Non-existent body parameter
        // shippingCallbackUrl" until the feature is released for the account. Everything behind it
        // (RouteBuilder::getExpressComponentsShippingCallbackUrl and the api route) is in place, so
        // enabling it is a one line change here.
        $country = $salesChannelContext->getShippingLocation()->getCountry();
        $address = new ShippingCallbackAddress((string) $country->getIso());

        $createSession->setShippingOptions($this->shippingOptionsResolver->resolve($address, $salesChannelContext));
    }

    private function buildDescription(SalesChannelContext $salesChannelContext): string
    {
        return (string) $salesChannelContext->getSalesChannel()->getName();
    }

    private function applyCustomer(CreateSession $createSession, SalesChannelContext $salesChannelContext): void
    {
        $createSession->setRequiredCustomerDetails(self::REQUIRED_CUSTOMER_DETAILS);

        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            return;
        }

        $email = $customer->getEmail();

        $billingAddress = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        if ($billingAddress instanceof CustomerAddressEntity) {
            $createSession->setBillingAddress(Address::fromCustomerAddress($billingAddress, $email));
        }

        $shippingAddress = $customer->getActiveShippingAddress() ?? $customer->getDefaultShippingAddress();
        if ($shippingAddress instanceof CustomerAddressEntity) {
            $createSession->setShippingAddress(Address::fromCustomerAddress($shippingAddress, $email));
        }

        $mollieCustomerId = $this->getMollieCustomerId($customer, $salesChannelContext->getSalesChannelId());
        if ($mollieCustomerId !== null) {
            $createSession->setCustomerId($mollieCustomerId);
        }
    }

    private function getMollieCustomerId(CustomerEntity $customer, string $salesChannelId): ?string
    {
        $customerExtension = $customer->getExtension(Mollie::EXTENSION);
        if (! $customerExtension instanceof Customer) {
            return null;
        }

        $apiSettings = $this->settings->getApiSettings($salesChannelId);

        return $customerExtension->getForProfileId($apiSettings->getProfileId(), $apiSettings->getMode());
    }
}
