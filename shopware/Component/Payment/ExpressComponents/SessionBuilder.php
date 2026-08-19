<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateSession;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SessionBuilder implements SessionBuilderInterface
{
    /**
     * Details Mollie has to collect from the shopper inside the express component
     * when no logged in customer provides them.
     */
    private const GUEST_REQUIRED_CUSTOMER_DETAILS = ['email', 'billing-address', 'shipping-address'];

    public function __construct(
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: SessionStorage::class)]
        private SessionStorageInterface $sessionStorage,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function buildFromProduct(SalesChannelProductEntity $product, SalesChannelContext $salesChannelContext): Session
    {
        $productId = $product->getId();
        $customerId = $salesChannelContext->getCustomer()?->getId();

        $existingSession = $this->loadExistingSession($productId, $customerId, $salesChannelContext);
        if ($existingSession instanceof Session) {
            return $existingSession;
        }

        $session = $this->sessionGateway->createSession(
            $this->buildCreateSession($product, $salesChannelContext),
            $salesChannelContext
        );

        $this->sessionStorage->set($productId, $customerId, $session->getId());

        return $session;
    }

    private function loadExistingSession(string $productId, ?string $customerId, SalesChannelContext $salesChannelContext): ?Session
    {
        $sessionId = $this->sessionStorage->get($productId, $customerId);
        if ($sessionId === null) {
            return null;
        }

        try {
            return $this->sessionGateway->getSession($sessionId, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->warning('Stored express components session could not be loaded, creating a new one', [
                'error' => $exception->getMessage(),
                'sessionId' => $sessionId,
                'productId' => $productId,
                'customerId' => $customerId,
            ]);

            $this->sessionStorage->remove($productId, $customerId);

            return null;
        }
    }

    private function buildCreateSession(SalesChannelProductEntity $product, SalesChannelContext $salesChannelContext): CreateSession
    {
        $currencyIso = $salesChannelContext->getCurrency()->getIsoCode();
        $calculatedPrice = $product->getCalculatedPrice();

        $unitPriceValue = $calculatedPrice->getUnitPrice();
        if ($salesChannelContext->getTaxState() === CartPrice::TAX_STATE_NET) {
            $unitPriceValue += $calculatedPrice->getCalculatedTaxes()->getAmount() / max(1, $calculatedPrice->getQuantity());
        }

        $description = (string) ($product->getTranslation('name') ?? $product->getName());
        $amount = new Money($unitPriceValue, $currencyIso);

        $lineItem = new LineItem($description, 1, new Money($unitPriceValue, $currencyIso), $amount);
        $lineItem->setSku($product->getProductNumber());

        $lines = new LineItemCollection();
        $lines->add($lineItem);

        $createSession = new CreateSession(
            $description,
            $this->routeBuilder->getExpressComponentsRedirectUrl(),
            $amount
        );
        $createSession->setCancelUrl($this->routeBuilder->getExpressComponentsCancelUrl());
        $createSession->setLines($lines);

        $this->applyCustomer($createSession, $salesChannelContext);

        return $createSession;
    }

    private function applyCustomer(CreateSession $createSession, SalesChannelContext $salesChannelContext): void
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            $createSession->setRequiredCustomerDetails(self::GUEST_REQUIRED_CUSTOMER_DETAILS);

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
