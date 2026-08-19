<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Mollie\Shopware\Component\Payment\ExpressMethod\TempAddress;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

/**
 * Builds the shipping options Mollie shows inside the express component.
 *
 * Which shipping methods are available and what they cost depends on the shipping country,
 * so the context is switched to the country Mollie reports before the methods are loaded and
 * the cart is recalculated for each of them.
 */
final class ShippingOptionsResolver implements ShippingOptionsResolverInterface
{
    /**
     * @param EntityRepository<CountryCollection<CountryEntity>> $countryRepository
     * @param EntityRepository<CustomerAddressCollection<CustomerEntity>> $customerAddressRepository
     */
    public function __construct(
        #[Autowire(service: ShippingMethodRoute::class)]
        private AbstractShippingMethodRoute $shippingMethodRoute,
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        private CartService $cartService,
        #[Autowire(service: 'country.repository')]
        private EntityRepository $countryRepository,
        #[Autowire(service: 'customer_address.repository')]
        private EntityRepository $customerAddressRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function resolve(ShippingCallbackAddress $address, SalesChannelContext $salesChannelContext): ShippingOptionCollection
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'countryIso' => $address->getCountry(),
            'postalCode' => $address->getPostalCode(),
            'salesChannelId' => $salesChannelId,
        ];

        $shippingOptions = new ShippingOptionCollection();

        $countryId = $this->getCountryId($address->getCountry(), $salesChannelContext);
        if ($countryId === null) {
            $this->logger->warning('No shipping country found for express components shipping options', $logData);

            return $shippingOptions;
        }

        $countryContext = $this->switchCountry($countryId, $address, $salesChannelContext);
        $currencyIso = $countryContext->getCurrency()->getIsoCode();
        $selectedShippingMethodId = $salesChannelContext->getShippingMethod()->getId();

        $request = new Request();
        $request->query->set('onlyAvailable', '1');
        $shippingMethods = $this->shippingMethodRoute->load($request, $countryContext, new Criteria())->getShippingMethods();

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $methodContext = $this->switchShippingMethod($shippingMethod->getId(), $countryContext);
            $cart = $this->cartService->getCart($methodContext->getToken(), $methodContext);

            $description = (string) ($shippingMethod->getTranslation('name') ?? $shippingMethod->getName());
            $shippingCosts = $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice();

            $shippingOptions->add(new ShippingOption(
                $description,
                $shippingMethod->getId(),
                new Money($shippingCosts, $currencyIso)
            ));
        }

        // leave the shopper's context the way it was found
        $this->switchShippingMethod($selectedShippingMethodId, $countryContext);
        $this->deleteTempAddress($salesChannelContext);

        $logData['total'] = $shippingOptions->count();
        $this->logger->info('Shipping options for express components resolved', $logData);

        return $shippingOptions;
    }

    private function switchCountry(string $countryId, ShippingCallbackAddress $address, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::COUNTRY_ID, $countryId);

        $customer = $salesChannelContext->getCustomer();
        $requestDataBag = $this->addTempAddress($requestDataBag, $countryId, $address, $salesChannelContext);
        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        return $this->salesChannelContextService->get(new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $customer?->getId(),
        ));
    }

    private function switchShippingMethod(string $shippingMethodId, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::SHIPPING_METHOD_ID, $shippingMethodId);

        $customer = $salesChannelContext->getCustomer();
        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        return $this->salesChannelContextService->get(new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $customer?->getId(),
        ));
    }

    /**
     * For a logged in customer Shopware prices the delivery with the address on the account
     * and ignores the country of the context, so the wallet address is written as a
     * temporary address and removed again once the options are built.
     */
    private function addTempAddress(RequestDataBag $requestDataBag, string $countryId, ShippingCallbackAddress $address, SalesChannelContext $salesChannelContext): RequestDataBag
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            return $requestDataBag;
        }

        $tempAddress = new TempAddress($customer, $countryId, $address->getCity(), $address->getPostalCode());
        $tempAddressId = TempAddress::getId($customer);

        $this->customerAddressRepository->upsert([$tempAddress->toUpsertArray()], $salesChannelContext->getContext());

        $requestDataBag->set(SalesChannelContextService::CUSTOMER_ID, $customer->getId());
        $requestDataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $tempAddressId);
        $requestDataBag->set(SalesChannelContextService::BILLING_ADDRESS_ID, $tempAddressId);

        return $requestDataBag;
    }

    private function deleteTempAddress(SalesChannelContext $salesChannelContext): void
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            return;
        }

        $this->customerAddressRepository->delete([['id' => TempAddress::getId($customer)]], $salesChannelContext->getContext());
    }

    private function getCountryId(string $countryIso, SalesChannelContext $salesChannelContext): ?string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('active', 1));
        $criteria->addFilter(new EqualsFilter('shippingAvailable', 1));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelContext->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('iso', $countryIso));

        return $this->countryRepository->searchIds($criteria, $salesChannelContext->getContext())->firstId();
    }
}
