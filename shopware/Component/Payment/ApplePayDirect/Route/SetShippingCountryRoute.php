<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\FakeApplePayAddress;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class SetShippingCountryRoute extends AbstractSetShippingCountryRoute
{
    /**
     * @param EntityRepository<CountryCollection<CountryEntity>> $countryRepository
     * @param EntityRepository<CustomerAddressCollection<CustomerAddressEntity>> $customerAddressRepository
     */
    public function __construct(
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'country.repository')]
        private EntityRepository $countryRepository,
        #[Autowire(service: 'customer_address.repository')]
        private EntityRepository $customerAddressRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractSetShippingCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.set-shipping-methods', path: '/store-api/mollie/applepay/shipping-method', methods: ['POST'])]
    public function setCountry(Request $request, SalesChannelContext $salesChannelContext): SetShippingCountryResponse
    {
        $countryCode = $request->get('countryCode');
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'countryCode' => $countryCode,
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->info('Start - set shipping country for apple pay', $logData);
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

        if ($customer instanceof CustomerEntity) {
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
        }

        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        $salesChannelContextServiceParameters = new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $customerId
        );
        $newContext = $this->salesChannelContextService->get($salesChannelContextServiceParameters);
        $this->logger->info('Finished - set shipping country for apple pay', $logData);

        return new SetShippingCountryResponse($newContext);
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
