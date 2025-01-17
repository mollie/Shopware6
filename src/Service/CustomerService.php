<?php

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Kiener\MolliePayments\Struct\CustomerStruct;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerService implements CustomerServiceInterface
{
    public const CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID = 'customer_id';
    public const CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN = 'credit_card_token';
    public const CUSTOM_FIELDS_KEY_MANDATE_ID = 'mandate_id';
    public const CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL = 'shouldSaveCardDetail';
    public const CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER = 'preferred_ideal_issuer';
    public const CUSTOM_FIELDS_KEY_PREFERRED_POS_TERMINAL = 'preferred_pos_terminal';
    public const CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID = 'express_address_id';

    /**
     * @var EntityRepository
     */
    private $countryRepository;

    /**
     * @var EntityRepository
     */
    private $customerRepository;

    /** @var Customer */
    private $customerApiService;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var LoggerInterface */
    private $logger;

    /** @var SalesChannelContextPersister */
    private $salesChannelContextPersister;

    /** @var EntityRepository */
    private $salutationRepository;

    /** @var SettingsService */
    private $settingsService;

    /**
     * @var ConfigService
     */
    private $configService;


    /** @var string */
    private $shopwareVersion;


    /**
     * @var ContainerInterface
     */
    private $container;

    /** @var EntityRepository */
    private $customerAddressRepository;


    /**
     * @param EntityRepository $countryRepository
     * @param EntityRepository $customerRepository
     * @param Customer $customerApiService
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param SalesChannelContextPersister $salesChannelContextPersister
     * @param EntityRepository $salutationRepository
     * @param SettingsService $settingsService
     * @param string $shopwareVersion
     * @param ConfigService $configService
     */
    public function __construct(
        EntityRepository         $countryRepository,
        EntityRepository        $customerRepository,
        EntityRepository $customerAddressRepository,
        Customer                           $customerApiService,
        EventDispatcherInterface           $eventDispatcher,
        LoggerInterface                    $logger,
        SalesChannelContextPersister       $salesChannelContextPersister,
        EntityRepository      $salutationRepository,
        SettingsService                    $settingsService,
        string                             $shopwareVersion,
        ConfigService                      $configService,
        ContainerInterface                 $container //we have to inject the container, because in SW 6.4.20.2 we have circular injection for the register route
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerRepository = $customerRepository;
        $this->customerApiService = $customerApiService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
        $this->salutationRepository = $salutationRepository;
        $this->settingsService = $settingsService;
        $this->shopwareVersion = $shopwareVersion;
        $this->configService = $configService;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->container = $container;
    }

    /**
     * Login the customer.
     *
     * @param CustomerEntity $customer
     * @param SalesChannelContext $context
     *
     * @return null|string
     */
    public function loginCustomer(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        /** @var null|string $newToken */
        $newToken = null;

        /** @var CustomerBeforeLoginEvent $event */
        $event = new CustomerBeforeLoginEvent($context, $customer->getEmail());

        // Dispatch the before login event
        $this->eventDispatcher->dispatch($event);

        /** @var string $newToken */
        $newToken = $this->salesChannelContextPersister->replace($context->getToken(), $context);

        // Persist the new token
        if (version_compare($this->shopwareVersion, '6.3.3', '<')) {
            // Shopware 6.3.2.x and lower
            $params = [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ];

            /** @phpstan-ignore-next-line */
            $this->salesChannelContextPersister->save($newToken, $params);
        } elseif (version_compare($this->shopwareVersion, '6.3.4', '<') && version_compare($this->shopwareVersion, '6.3.3', '>=')) {
            // Shopware 6.3.3.x
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                $customer->getId()
            );
        } else {
            // Shopware 6.3.4+
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                $context->getSalesChannel()->getId(),
                $customer->getId()
            );
        }

        /** @var CustomerLoginEvent $event */
        $event = new CustomerLoginEvent($context, $customer, $newToken);

        // Dispatch the customer login event
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    public function isCustomerLoggedIn(SalesChannelContext $context): bool
    {
        return ($context->getCustomer() instanceof CustomerEntity);
    }

    /**
     * Stores the credit card token in the custom fields of the customer.
     *
     * @param CustomerEntity $customer
     * @param string $cardToken
     * @param SalesChannelContext $context
     * @param bool $shouldSaveCardDetail
     * @return EntityWrittenContainerEvent
     */
    public function setCardToken(CustomerEntity $customer, string $cardToken, SalesChannelContext $context, bool $shouldSaveCardDetail = false): EntityWrittenContainerEvent
    {
        // Get existing custom fields
        $customFields = $customer->getCustomFields();

        // If custom fields are empty, create a new array
        if (! is_array($customFields)) {
            $customFields = [];
        }

        // Store the card token in the custom fields
        $customFields[CustomFieldsInterface::MOLLIE_KEY][self::CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN] = $cardToken;
        // Store shouldSaveCardDetail in the custom fields
        $customFields[CustomFieldsInterface::MOLLIE_KEY][self::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL] = $shouldSaveCardDetail;

        $this->logger->debug("Setting Credit Card Token", [
            'customerId' => $customer->getId(),
            'customFields' => $customFields,
        ]);

        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context->getContext());
    }

    /**
     * Stores the credit mandate id in the custom fields of the customer.
     *
     * @param CustomerEntity $customer
     * @param string $mandateId
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function setMandateId(CustomerEntity $customer, string $mandateId, Context $context): EntityWrittenContainerEvent
    {
        // Get existing custom fields
        $customFields = $customer->getCustomFields() ?? [];

        // Store the mandate id in the custom fields
        $customFields[CustomFieldsInterface::MOLLIE_KEY][self::CUSTOM_FIELDS_KEY_MANDATE_ID] = $mandateId;

        $this->logger->debug("Setting Credit Card Mandate Id", [
            'customerId' => $customer->getId(),
            'customFields' => $customFields,
        ]);

        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * @param string $customerID
     * @param array<mixed> $customFields
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function saveCustomerCustomFields(string $customerID, array $customFields, Context $context): EntityWrittenContainerEvent
    {
        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customerID,
            'customFields' => $customFields
        ]], $context);
    }


    /**
     * @param CustomerEntity $customer
     * @param string $terminalId
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function setPosTerminal(CustomerEntity $customer, string $terminalId, Context $context): EntityWrittenContainerEvent
    {
        $customFields = $customer->getCustomFields();

        if (! is_array($customFields)) {
            $customFields = [];
        }

        $customFields[CustomFieldsInterface::MOLLIE_KEY][self::CUSTOM_FIELDS_KEY_PREFERRED_POS_TERMINAL] = $terminalId;

        return $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * @param string $customerId
     * @param string $salesChannelId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return string
     */
    public function getMollieCustomerId(string $customerId, string $salesChannelId, Context $context): string
    {
        $settings = $this->settingsService->getSettings($salesChannelId);
        $struct = $this->getCustomerStruct($customerId, $context);

        return $struct->getCustomerId((string)$settings->getProfileId(), $settings->isTestMode());
    }

    /**
     * @param string $customerId
     * @param string $mollieCustomerId
     * @param string $profileId
     * @param bool $testMode
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     */
    public function setMollieCustomerId(string $customerId, string $mollieCustomerId, string $profileId, bool $testMode, Context $context): void
    {
        $existingStruct = $this->getCustomerStruct($customerId, $context);

        $existingStruct->setCustomerId($mollieCustomerId, $profileId, $testMode);

        $customFields = $existingStruct->toCustomFieldsArray();

        $this->saveCustomerCustomFields($customerId, $customFields, $context);
    }

    /**
     * Return a customer entity with address associations.
     *
     * @param string $customerId
     * @param Context $context
     * @return null|CustomerEntity
     */
    public function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $customer = null;

        try {
            $criteria = new Criteria([$customerId]);

            $criteria->addAssociations([
                'defaultShippingAddress.country',
                'defaultBillingAddress.country',
            ]);

            /** @var CustomerEntity $customer */
            $customer = $this->customerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
            // Should error be (re)thrown here, instead of returning null?
        }

        return $customer;
    }

    /**
     * @param string $customerId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return CustomerStruct
     */
    public function getCustomerStruct(string $customerId, Context $context): CustomerStruct
    {
        $struct = new CustomerStruct();

        $customer = $this->getCustomer($customerId, $context);

        if (! ($customer instanceof CustomerEntity)) {
            throw new CustomerCouldNotBeFoundException($customerId);
        }

        $customFields = $customer->getCustomFields() ?? [];

        // If there is a legacy customer id, set it separately
        if (isset($customFields[self::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID])) {
            $struct->setLegacyCustomerId($customFields[self::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID]);
        }
        $molliePaymentsCustomFields = $customFields[CustomFieldsInterface::MOLLIE_KEY] ?? [];
        if (! is_array($molliePaymentsCustomFields)) {
            $this->logger->warning('Customer customFields for MolliePayments are invalid. Array is expected', [
                'currentCustomFields' => $molliePaymentsCustomFields
            ]);
            $molliePaymentsCustomFields = [];
        }
        // Then assign all custom fields under the mollie_payments key
        $struct->assign($molliePaymentsCustomFields);

        return $struct;
    }

    /**
     * Return an array of address data.
     *
     * @param null|CustomerAddressEntity|OrderAddressEntity $address
     * @param CustomerEntity $customer
     * @return array<mixed>
     */
    public function getAddressArray($address, CustomerEntity $customer): array
    {
        if ($address === null) {
            return [];
        }

        return [
            'title' => $address->getSalutation() !== null ? $address->getSalutation()->getDisplayName() . '.' : null,
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $customer->getEmail(),
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() !== null ? $address->getCountry()->getIso() : 'NL',
        ];
    }

    /**
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @param string $phone
     * @param string $street
     * @param string $zipCode
     * @param string $city
     * @param string $countryISO2
     * @param SalesChannelContext $context
     * @return null|CustomerEntity
     */
    public function createApplePayDirectCustomer(string $firstname, string $lastname, string $email, string $phone, string $street, string $zipCode, string $city, string $countryISO2, SalesChannelContext $context): ?CustomerEntity
    {
        $countryId = $this->getCountryId($countryISO2, $context->getContext());
        $salutationId = $this->getSalutationId($context->getContext());

        $data = new RequestDataBag();
        $data->set('salutationId', $salutationId);
        $data->set('guest', true);
        $data->set('firstName', $firstname);
        $data->set('lastName', $lastname);
        $data->set('email', $email);

        $billingAddress = new RequestDataBag();
        $billingAddress->set('street', $street);
        $billingAddress->set('zipcode', $zipCode);
        $billingAddress->set('city', $city);
        $billingAddress->set('phoneNumber', $phone);
        $billingAddress->set('countryId', $countryId);

        $data->set('billingAddress', $billingAddress);

        try {
            $abstractRegisterRoute = $this->container->get(RegisterRoute::class);
            $response = $abstractRegisterRoute->register($data, $context, false);
            return $response->getCustomer();
        } catch (ConstraintViolationException $e) {
            $errors = [];
            /** we have to store the errors in an array because getErrors returns a generator */
            foreach ($e->getErrors() as $error) {
                $errors[]=$error;
            }
            $this->logger->error($e->getMessage(), ['errors'=>$errors]);
            return null;
        }
    }

    /**
     * Returns a country id by its iso code.
     *
     * @param string $countryCode
     * @param Context $context
     *
     * @return null|string
     */
    public function getCountryId(string $countryCode, Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', strtoupper($countryCode)));

            // Get countries
            /** @var string[] $countries */
            $countries = $this->countryRepository->searchIds($criteria, $context)->getIds();

            return ! empty($countries) ? (string)$countries[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns a salutation id by its key.
     *
     * @param Context $context
     *
     * @return null|string
     */
    public function getSalutationId(Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salutationKey', 'not_specified'));

            // Get salutations
            /** @var string[] $salutations */
            $salutations = $this->salutationRepository->searchIds($criteria, $context)->getIds();

            return ! empty($salutations) ? (string)$salutations[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $customerId
     * @param string $salesChannelId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @throws CouldNotCreateMollieCustomerException
     */
    public function createMollieCustomer(string $customerId, string $salesChannelId, Context $context): void
    {
        $settings = $this->settingsService->getSettings($salesChannelId);
        $struct = $this->getCustomerStruct($customerId, $context);

        if (empty($settings->getProfileId())) {
            $this->logger->warning('No profile ID available, fetch new Profile Id', [
                'saleschannel' => $salesChannelId,
                'customerId' => $customerId,
            ]);

            // auto-fix missing profile IDs
            $this->configService->fetchProfileId($salesChannelId);

            // refresh settings with new fetched profile id
            $settings = $this->settingsService->getSettings($salesChannelId);
        }

        if ($this->customerApiService->isLegacyCustomerValid($struct->getLegacyCustomerId(), $salesChannelId)) {
            $this->setMollieCustomerId(
                $customerId,
                (string)$struct->getLegacyCustomerId(),
                (string)$settings->getProfileId(),
                $settings->isTestMode(),
                $context
            );

            $struct->setLegacyCustomerId(null);
            $struct->setCustomerId(
                (string)$struct->getLegacyCustomerId(),
                (string)$settings->getProfileId(),
                $settings->isTestMode()
            );

            return;
        }

        $mollieCustomerId = $struct->getCustomerId((string)$settings->getProfileId(), $settings->isTestMode());
        try {
            $this->customerApiService->getMollieCustomerById($mollieCustomerId, $salesChannelId);
            return;
        } catch (CouldNotFetchMollieCustomerException $e) {
            $this->logger->warning('No customer found for the current mollie id and sales channel combination, creating a new one.', [
                'salesChannel' => $salesChannelId,
                'mollieCustomerId' => $mollieCustomerId,
            ]);
        }

        $customer = $this->getCustomer($customerId, $context);

        if (! ($customer instanceof CustomerEntity)) {
            throw new CustomerCouldNotBeFoundException($customerId);
        }

        $mollieCustomer = $this->customerApiService->createCustomerAtMollie($customer, $salesChannelId);

        $this->setMollieCustomerId(
            $customerId,
            $mollieCustomer->id,
            (string)$settings->getProfileId(),
            $settings->isTestMode(),
            $context
        );
    }


    public function findCustomerByEmail(string $email, SalesChannelContext $context): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new EqualsFilter('guest', true));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));


        $searchResult = $this->customerRepository->search($criteria, $context->getContext());

        if ($searchResult->getTotal() === 0) {
            return null;
        }

        /** @var CustomerEntity $foundCustomer */
        $foundCustomer = $searchResult->first();

        $boundCustomers = $searchResult->filter(function (CustomerEntity $customerEntity) use ($context) {
            return $customerEntity->getBoundSalesChannelId() === $context->getSalesChannelId();
        });

        if ($boundCustomers->getTotal() > 0) {
            $foundCustomer = $boundCustomers->first();
        }

        $bindCustomers = $this->configService->getSystemConfigService()->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');

        if ($bindCustomers && $foundCustomer->getBoundSalesChannelId() === null) {
            return null;
        }
        return $foundCustomer;
    }

    public function reuseOrCreateAddresses(CustomerEntity $customer, AddressStruct $shippingAddress, Context $context, ?AddressStruct $billingAddress = null): EntityWrittenContainerEvent
    {
        $mollieAddressIds = [$shippingAddress->getMollieAddressId()];
        if ($billingAddress !== null) {
            $mollieAddressIds[] = $billingAddress->getMollieAddressId();
        }
        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('customerId', $customer->getId()),
            new EqualsAnyFilter('customFields.' . CustomFieldsInterface::MOLLIE_KEY . '.' . self::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID, $mollieAddressIds)
        ]));

        $customerAddressSearchResult = $this->customerAddressRepository->search($criteria, $context);

        // if we dont find any address for customer we create new once
        if ($customerAddressSearchResult->getTotal() === 0) {
            $shippingAddressId = Uuid::randomHex();
            $billingAddressId = $shippingAddressId;

            $addresses = [
                $this->createShopwareAddressArray($shippingAddressId, $customer->getId(), $customer->getSalutationId(), $shippingAddress, $context)
            ];
            if ($billingAddress !== null) {
                $billingAddressId = Uuid::randomHex();
                $addresses[] = $this->createShopwareAddressArray($billingAddressId, $customer->getId(), $customer->getSalutationId(), $billingAddress, $context);
            }

            $customer = [
                'id' => $customer->getId(),
                'defaultBillingAddressId' => $shippingAddressId,
                'defaultShippingAddressId' => $billingAddressId,
                'addresses' => $addresses
            ];

            return $this->customerRepository->upsert([$customer], $context);
        }


        $defaultShippingAddressId = null;
        $defaultBillingAddressId = null;


        /** @var CustomerAddressEntity $customerAddress */
        foreach ($customerAddressSearchResult->getElements() as $customerAddress) {
            $addressCustomFields = $customerAddress->getCustomFields();

            if ($addressCustomFields === null) {
                continue;
            }

            // skip addresses without custom fields, those are configured by the customer in backend
            $mollieAddressId = $addressCustomFields[CustomFieldsInterface::MOLLIE_KEY][self::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID] ?? null;
            if ($mollieAddressId === null) {
                continue;
            }
            // try to find default shipping and billing address and store them for later
            if ($mollieAddressId === $shippingAddress->getMollieAddressId()) {
                $defaultShippingAddressId = $customerAddress->getId();
            }

            if ($billingAddress !== null && $mollieAddressId === $billingAddress->getMollieAddressId()) {
                $defaultBillingAddressId = $customerAddress->getId();
            }
        }

        //customer have addresses, might be from old PPE orders, might be from shopware, lets find them and select them
        $addresses = [];

        // we havent found a default adress, create a new one
        if ($defaultShippingAddressId === null) {
            $defaultShippingAddressId = Uuid::randomHex();
            $addresses[] = $this->createShopwareAddressArray($defaultShippingAddressId, $customer->getId(), $customer->getSalutationId(), $shippingAddress, $context);
        }

        //we have a billing address but we didnt found them in saved addresses, create new one
        if ($billingAddress !== null && $defaultBillingAddressId === null) {
            $defaultBillingAddressId = Uuid::randomHex();
            $addresses[] = $this->createShopwareAddressArray($defaultBillingAddressId, $customer->getId(), $customer->getSalutationId(), $billingAddress, $context);
        }

        //we dont have a billing adress, we use the shipping adress as billing
        if ($billingAddress === null && $defaultBillingAddressId === null) {
            $defaultBillingAddressId = $defaultShippingAddressId;
        }
        $customer = [
            'id' => $customer->getId(),
            'defaultBillingAddressId' => $defaultBillingAddressId,
            'defaultShippingAddressId' => $defaultBillingAddressId,
        ];

        if (count($addresses) > 0) {
            $customer['addresses'] = $addresses;
        }
        return $this->customerRepository->upsert([$customer], $context);
    }

    public function createGuestAccount(AddressStruct $shippingAddress, string $paymentMethodId, SalesChannelContext $context, ?int $acceptedDataProtection, ?AddressStruct $billingAddress = null): ?CustomerEntity
    {
        $countryId = $this->getCountryId($shippingAddress->getCountryCode(), $context->getContext());
        $salutationId = $this->getSalutationId($context->getContext());

        $data = new RequestDataBag();
        $data->set('salutationId', $salutationId);
        $data->set('guest', true);
        $data->set('firstName', $shippingAddress->getFirstName());
        $data->set('lastName', $shippingAddress->getLastName());
        $data->set('email', $shippingAddress->getEmail());

        $settings = $this->settingsService->getSettings($context->getSalesChannelId());
        if ($settings->isRequireDataProtectionCheckbox()) {
            $data->set('acceptedDataProtection', (bool)$acceptedDataProtection);
        }



        $shippingAddressData = new RequestDataBag();
        $shippingAddressData->set('firstName', $shippingAddress->getFirstName());
        $shippingAddressData->set('lastName', $shippingAddress->getLastName());
        $shippingAddressData->set('street', $shippingAddress->getStreet());
        $shippingAddressData->set('additionalAddressLine1', $shippingAddress->getStreetAdditional());
        $shippingAddressData->set('zipcode', $shippingAddress->getZipCode());
        $shippingAddressData->set('city', $shippingAddress->getCity());
        $shippingAddressData->set('countryId', $countryId);
        $customFields = new RequestDataBag();
        $customFields->set(CustomerAddressDefinition::ENTITY_NAME, [
            CustomFieldsInterface::MOLLIE_KEY => [self::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID => $shippingAddress->getMollieAddressId()]
        ]);
        $shippingAddressData->set('customFields', $customFields);
        $data->set('shippingAddress', $shippingAddressData);
        $data->set('billingAddress', $shippingAddressData);

        if ($billingAddress !== null) {
            $countryId = $this->getCountryId($billingAddress->getCountryCode(), $context->getContext());

            $billingAddressData = new RequestDataBag();
            $billingAddressData->set('street', $billingAddress->getStreet());
            $billingAddressData->set('additionalAddressLine1', $billingAddress->getStreetAdditional());
            $billingAddressData->set('zipcode', $billingAddress->getZipCode());
            $billingAddressData->set('city', $billingAddress->getCity());
            $billingAddressData->set('countryId', $countryId);
            $customFields = new RequestDataBag();
            $customFields->set(CustomerAddressDefinition::ENTITY_NAME, [
                CustomFieldsInterface::MOLLIE_KEY => [self::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID => $billingAddress->getMollieAddressId()]
            ]);
            $billingAddressData->set('customFields', $customFields);

            $data->set('billingAddress', $shippingAddressData);
        }

        try {
            $abstractRegisterRoute = $this->container->get(RegisterRoute::class);
            $response = $abstractRegisterRoute->register($data, $context, false);
            return $response->getCustomer();
        } catch (ConstraintViolationException $e) {
            $errors = [];
            /** we have to store the errors in an array because getErrors returns a generator */
            foreach ($e->getErrors() as $error) {
                $errors[] = $error;
            }
            $this->logger->error($e->getMessage(), ['errors' => $errors]);
            return null;
        }
    }


    /**
     * @param string $addressId
     * @param string $customerId
     * @param null|string $salutationId
     * @param AddressStruct $address
     * @param Context $context
     * @return array<mixed>
     */
    private function createShopwareAddressArray(string $addressId, string $customerId, ?string $salutationId, AddressStruct $address, Context $context): array
    {
        $addressArray = [
            'id' => $addressId,
            'customerId' => $customerId,
            'countryId' => $this->getCountryId($address->getCountryCode(), $context),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'additionalAddressLine1' => $address->getStreetAdditional(),
            'zipcode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'phoneNumber' => '',
            'customFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    self::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID => $address->getMollieAddressId()
                ]
            ]
        ];
        if ($salutationId !== null) {
            $addressArray['salutationId'] = $salutationId;
        }
        return $addressArray;
    }
}
