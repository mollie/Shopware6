<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateSession;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Session;
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
     * Details Mollie collects from the shopper inside the express component. They are sent for a
     * logged in customer too: Mollie rejects a session that offers shippingOptions without asking
     * for a shipping address, and the shopper may well pick another address inside the wallet than
     * the one on the account.
     */
    private const REQUIRED_CUSTOMER_DETAILS = ['email', 'billing-address', 'shipping-address'];

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
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function buildFromCart(Cart $cart, SalesChannelContext $salesChannelContext): Session
    {
        $amount = new Money($this->getAmountWithoutShipping($cart), $salesChannelContext->getCurrency()->getIsoCode());

        $existingSession = $this->loadExistingSession($cart, $amount, $salesChannelContext);
        if ($existingSession instanceof Session) {
            return $existingSession;
        }

        $session = $this->sessionGateway->createSession(
            $this->buildCreateSession($cart, $amount, $salesChannelContext),
            $salesChannelContext
        );

        $cart->addExtension(self::CART_EXTENSION, $session);
        $this->cartPersister->save($cart, $salesChannelContext);

        return $session;
    }

    /**
     * A session cannot be edited after it was created, so it is only reused while it still
     * matches the cart. Any change to the total means a new session has to be created.
     */
    private function loadExistingSession(Cart $cart, Money $amount, SalesChannelContext $salesChannelContext): ?Session
    {
        $storedSession = $cart->getExtension(self::CART_EXTENSION);
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
