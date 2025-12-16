<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingMethod;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\FakeApplePayAddress;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class GetShippingMethodsRoute extends AbstractGetShippingMethodsRoute
{
    /**
     * @param EntityRepository<CustomerAddressCollection<CustomerEntity>> $customerAddressRepository
     * @param EntityRepository<CountryCollection<CountryEntity>> $countryRepository
     */
    public function __construct(
        #[Autowire(service: CheckoutGatewayRoute::class)]
        private AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        #[Autowire(service: SetShippingMethodRoute::class)]
        private AbstractSetShippingMethodRoute $setShippingMethodRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: GetCartRoute::class)]
        private AbstractGetCartRoute $getCartRoute,
        #[Autowire(service: 'customer_address.repository')]
        private EntityRepository $customerAddressRepository,
        #[Autowire(service: 'country.repository')]
        private EntityRepository $countryRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractGetShippingMethodsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/applepay/shipping-methods', name: 'store-api.mollie.apple-pay.shipping-methods', methods: ['POST'])]
    public function methods(Request $request, SalesChannelContext $salesChannelContext): GetShippingMethodsResponse
    {
        $countryCode = $request->get('countryCode');

        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'countryCode' => $countryCode,
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->info('Start - set shipping country for apple pay', $logData);
        if ($countryCode === null) {
            $this->logger->error('Failed to set shipping country for apple pay, country code is not provided', $logData);
            throw ApplePayDirectException::countryCodeEmpty();
        }
        $countryId = $this->getCountryId($countryCode, $salesChannelContext);
        if ($countryId === null) {
            $this->logger->error('Failed to find shipping country for apple pay', $logData);
            throw ApplePayDirectException::invalidCountryCode($countryCode);
        }

        $logData['countryId'] = $countryId;
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::COUNTRY_ID, $countryId);

        $customer = $salesChannelContext->getCustomer();

        $customerId = null;
        if ($customer !== null) {
            $customerId = $customer->getId();
        }

        $requestDataBag = $this->addFakeAddress($requestDataBag, $countryId, $logData, $salesChannelContext);

        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        $salesChannelContextServiceParameters = new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $customerId
        );

        $newContext = $this->salesChannelContextService->get($salesChannelContextServiceParameters);

        $this->logger->info('Finished - set shipping country for apple pay', $logData);

        $cartResponse = $this->getCartRoute->cart($request, $newContext);

        $request->query->set('onlyAvailable', '1');
        $checkoutResponse = $this->checkoutGatewayRoute->load($request, $cartResponse->getShopwareCart(), $salesChannelContext);

        $selectedShippingMethodId = $salesChannelContext->getShippingMethod()->getId();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $logData = [
            'shippingMethodId' => $selectedShippingMethodId,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - get shipping methods for apple pay express', $logData);

        $applePayMethods = [];
        $shippingMethods = $checkoutResponse->getShippingMethods();

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $detail = '';
            $shippingMethodId = $shippingMethod->getId();
            $deliveryTime = $shippingMethod->getDeliveryTime();
            if ($deliveryTime instanceof DeliveryTimeEntity) {
                $detail = (string) $deliveryTime->getName();
            }
            $tempContext = $this->setShippingMethod($shippingMethodId, $salesChannelContext);
            $cartResponse = $this->getCartRoute->cart($request, $tempContext);

            $cart = $cartResponse->getCart();
            $shippingCosts = $cart->getShippingAmount();

            $applePayMethods[$shippingMethodId] = new ApplePayShippingMethod($shippingMethod->getId(), (string) $shippingMethod->getName(), $detail, $shippingCosts);
        }

        $this->setShippingMethod($selectedShippingMethodId, $salesChannelContext);

        $applePayMethods = $this->setSelectedMethodToFirstElement($applePayMethods, $selectedShippingMethodId);

        $this->deleteFakeAddress($salesChannelContext);

        $this->logger->info('Finished - get shipping methods for apple pay express', $logData);

        return new GetShippingMethodsResponse($applePayMethods);
    }

    /**
     * @param array<mixed> $logData
     */
    public function addFakeAddress(RequestDataBag $requestDataBag, string $countryId, array $logData, SalesChannelContext $salesChannelContext): RequestDataBag
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return $requestDataBag;
        }
        $customerId = $customer->getId();
        $fakeApplePayAddress = new FakeApplePayAddress($customer, $countryId);
        $fakeApplePayAddressId = FakeApplePayAddress::getId($customer);
        $logData['customerId'] = $customerId;
        $logData['addressId'] = $fakeApplePayAddressId;
        $this->logger->info('Customer is logged in, fake apple pay address added for cart rules', $logData);

        $this->customerAddressRepository->upsert([$fakeApplePayAddress->toUpsertArray()], $salesChannelContext->getContext());

        $requestDataBag->set(SalesChannelContextService::CUSTOMER_ID, $customerId);
        $requestDataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $fakeApplePayAddressId);
        $requestDataBag->set(SalesChannelContextService::BILLING_ADDRESS_ID, $fakeApplePayAddressId);

        return $requestDataBag;
    }

    public function deleteFakeAddress(SalesChannelContext $salesChannelContext): void
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return;
        }

        $fakeAddressId = FakeApplePayAddress::getId($customer);
        $this->customerAddressRepository->delete([
            [
                'id' => $fakeAddressId,
            ]
        ], $salesChannelContext->getContext());
    }

    private function setShippingMethod(string $shippingMethodId, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $request = new Request();
        $request->attributes->set('identifier', $shippingMethodId);
        $setShippingMethodResponse = $this->setShippingMethodRoute->setShipping($request, $salesChannelContext);

        return $setShippingMethodResponse->getSalesChannelContext();
    }

    /**
     * @param ApplePayShippingMethod[] $applePayMethods
     *
     * @return ApplePayShippingMethod[]
     */
    private function setSelectedMethodToFirstElement(array $applePayMethods, string $selectedShippingMethodId): array
    {
        $selectedShippingMethod = $applePayMethods[$selectedShippingMethodId];

        unset($applePayMethods[$selectedShippingMethodId]);
        $applePayMethods = array_values($applePayMethods);
        array_unshift($applePayMethods, $selectedShippingMethod);

        return $applePayMethods;
    }

    private function getCountryId(string $countryCode, SalesChannelContext $salesChannelContext): ?string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('active', 1));
        $criteria->addFilter(new EqualsFilter('shippingAvailable', 1));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelContext->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('iso', $countryCode));

        $searchResult = $this->countryRepository->searchIds($criteria, $salesChannelContext->getContext());

        return $searchResult->firstId();
    }
}
