<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService as ShopwareAccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This is basically a copy of Shopware Account Service, was necessary,
 * because the AccountService from shopware ignore guest accounts
 * but we need guest accounts since we create them in express checkout
 */
final class AccountService extends AbstractAccountService
{
    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     * @param EntityRepository<CountryCollection<CountryEntity>> $countryRepository
     * @param EntityRepository<SalutationCollection<SalutationEntity>> $salutationRepository
     */
    public function __construct(
        #[Autowire(service: 'customer.repository')]
        private EntityRepository $customerRepository,
        #[Autowire(service: 'country.repository')]
        private EntityRepository $countryRepository,
        #[Autowire(service: 'salutation.repository')]
        private EntityRepository $salutationRepository,
        #[Autowire(service: RegisterRoute::class)]
        private AbstractRegisterRoute $registerRoute,
        private ShopwareAccountService $accountService,
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractAccountService
    {
        throw new DecorationPatternException(self::class);
    }

    public function loginOrCreateAccount(string $paymentMethodId, Address $billingAddress, Address $shippingAddress, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $currentPaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();

        $logData = [
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'currentPaymentMethodId' => $currentPaymentMethodId,
            'newPaymentMethodId' => $paymentMethodId,
        ];
        $email = $billingAddress->getEmail();
        $this->logger->debug('Start - login or create express customer', $logData);

        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            $this->logger->debug('Customer not logged in, try to login or create an account', $logData);
            try {
                $customer = $this->getCustomerByEmail($email, $salesChannelContext);
                $logData['customerId'] = $customer->getId();
                $logData['customerNumber'] = $customer->getCustomerNumber();
                $this->logger->debug('Customer was found by email', $logData);
                $this->accountService->loginById($customer->getId(), $salesChannelContext);
            } catch (\Throwable $e) {
                $customer = $this->createNewGuestAccount($billingAddress, $shippingAddress, $salesChannelContext);
                $logData['customerId'] = $customer->getId();
                $logData['customerNumber'] = $customer->getCustomerNumber();
                $this->logger->debug('New guest account created', $logData);
            }
        }

        $logData['paymentMethodId'] = $paymentMethodId;
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::PAYMENT_METHOD_ID, $paymentMethodId);
        $requestDataBag->set(SalesChannelContextService::CUSTOMER_ID, $customer->getId());
        $this->logger->debug('Switch customers payment method', $logData);
        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        $parameters = new SalesChannelContextServiceParameters($salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $customer->getId()
        );
        $this->logger->debug('Finished - login or create express customer', $logData);

        return $this->salesChannelContextService->get($parameters);
    }

    /**
     * @return array<string,string>
     */
    private function getCountryIsoMapping(Address $shippingAddress, Address $billingAddress, SalesChannelContext $salesChannelContext): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsAnyFilter('iso', [$shippingAddress->getCountry(), $billingAddress->getCountry()]));

        $countrySearchResult = $this->countryRepository->search($criteria, $salesChannelContext->getContext());

        $countryIsoMapping = [];
        /** @var CountryEntity $country */
        foreach ($countrySearchResult->getElements() as $country) {
            $countryIsoMapping[$country->getIso()] = $country->getId();
        }

        return $countryIsoMapping;
    }

    private function getCustomerByEmail(string $email, SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        $customer = $this->fetchCustomer($criteria, $salesChannelContext, true);
        if ($customer === null) {
            throw CustomerException::customerNotFound($email);
        }

        return $customer;
    }

    private function getNotSpecifiedSalutation(SalesChannelContext $context): SalutationEntity
    {
        $criteria = new Criteria();
        $salutationSearchResult = $this->salutationRepository->search($criteria, $context->getContext());
        $foundSalutation = null;
        /** @var SalutationEntity $salutation */
        foreach ($salutationSearchResult->getElements() as $salutation) {
            $foundSalutation = $salutation;
            if ($salutation->getSalutationKey() === 'not_specified') {
                return $foundSalutation;
            }
        }
        if ($foundSalutation === null) {
            throw new \Exception('Salutations not found, please add at least one salutation');
        }

        return $foundSalutation;
    }

    private function getStorefrontUrl(SalesChannelContext $salesChannelContext): ?string
    {
        $domainId = $salesChannelContext->getDomainId();
        if ($domainId === null) {
            return null;
        }
        $salesChannel = $salesChannelContext->getSalesChannel();
        $salesChannelDomains = $salesChannel->getDomains();
        if ($salesChannelDomains === null) {
            return null;
        }
        /** @var ?SalesChannelDomainEntity $salesChannelDomain */
        $salesChannelDomain = $salesChannelDomains->get($domainId);
        if ($salesChannelDomain === null) {
            return null;
        }

        return $salesChannelDomain->getUrl();
    }

    private function createNewGuestAccount(Address $billingAddress, Address $shippingAddress, SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $data = new DataBag();

        $storeFrontUrl = $this->getStorefrontUrl($salesChannelContext);
        if ($storeFrontUrl !== null) {
            $data->set('storeFrontUrl', $storeFrontUrl);
        }

        $countryIsoMapping = $this->getCountryIsoMapping($shippingAddress, $billingAddress, $salesChannelContext);
        $defaultSalutation = $this->getNotSpecifiedSalutation($salesChannelContext);

        $billingAddressData = new DataBag($billingAddress->toRegisterFormArray());
        $billingAddressData->set('countryId', $countryIsoMapping[$billingAddress->getCountry()] ?? null);
        $billingAddressData->set('salutationId', $defaultSalutation->getId());

        $shippingAddressData = new DataBag($shippingAddress->toRegisterFormArray());
        $shippingAddressData->set('countryId', $countryIsoMapping[$shippingAddress->getCountry()] ?? null);
        $shippingAddressData->set('salutationId', $defaultSalutation->getId());

        $data->set('guest', true);
        $data->set('salutationId', $defaultSalutation->getId());
        $data->set('firstName', $billingAddress->getGivenName());
        $data->set('lastName', $billingAddress->getFamilyName());
        $data->set('email', $billingAddress->getEmail());

        $data->set('billingAddress', $billingAddressData);
        $data->set('shippingAddress', $shippingAddressData);

        $registerRouteResponse = $this->registerRoute->register($data->toRequestDataBag(), $salesChannelContext, $data->has('storefrontUrl'));

        return $registerRouteResponse->getCustomer();
    }

    /**
     * This method filters for the standard customer related constraints like active or the sales channel
     * assignment.
     * Add only filters to the $criteria for values which have an index in the database, e.g. id, or email. The rest
     * should be done via PHP because it's a lot faster to filter a few entities on PHP side with the same email
     * address, than to filter a huge numbers of rows in the DB on a not indexed column.
     */
    private function fetchCustomer(Criteria $criteria, SalesChannelContext $context, bool $includeGuest = false): ?CustomerEntity
    {
        $criteria->setTitle('account-service::fetchCustomer');

        $result = $this->customerRepository->search($criteria, $context->getContext())->getEntities();
        $customers = $result->getElements();
        $resultArray = [];
        /** @var CustomerEntity $customer */
        foreach ($customers as $customer) {
            // Skip not active users
            if (! $customer->getActive()) {
                continue;
            }
            // Skip guest if not required
            if (! $includeGuest && $customer->getGuest()) {
                continue;
            }
            // It is bound, but not to the current one. Skip it
            if ($customer->getBoundSalesChannelId() !== null && $customer->getBoundSalesChannelId() !== $context->getSalesChannelId()) {
                continue;
            }
            $resultArray[] = $customer;
        }

        if (count($resultArray) === 0) {
            return null;
        }
        // If there is more than one account we want to return the latest, this is important
        // for guest accounts, real customer accounts should only occur once, otherwise the
        // wrong password will be validated
        if (count($resultArray) > 1) {
            usort($resultArray, function (CustomerEntity $a, CustomerEntity $b) {
                return $b->getCreatedAt() <=> $a->getCreatedAt();
            });
        }

        return $resultArray[0];
    }
}
