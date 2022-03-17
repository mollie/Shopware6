<?php

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Struct\CustomerStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerService
{
    public const CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID = 'customer_id';
    public const CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN = 'credit_card_token';
    public const CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER = 'preferred_ideal_issuer';

    /** @var EntityRepositoryInterface */
    private $countryRepository;

    /** @var EntityRepositoryInterface */
    private $customerRepository;

    /** @var Customer */
    private $customerApiService;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var LoggerInterface */
    private $logger;

    /** @var SalesChannelContextPersister */
    private $salesChannelContextPersister;

    /** @var EntityRepositoryInterface */
    private $salutationRepository;

    /** @var SettingsService */
    private $settingsService;

    /** @var string */
    private $shopwareVersion;

    /** @var NumberRangeValueGeneratorInterface */
    private $valueGenerator;

    /**
     * Creates a new instance of the customer service.
     *
     * @param EntityRepositoryInterface $countryRepository
     * @param EntityRepositoryInterface $customerRepository
     * @param Customer $customerApiService
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param SalesChannelContextPersister $salesChannelContextPersister
     * @param EntityRepositoryInterface $salutationRepository
     * @param SettingsService $settingsService
     * @param string $shopwareVersion
     * @param NumberRangeValueGeneratorInterface $valueGenerator
     */
    public function __construct(
        EntityRepositoryInterface          $countryRepository,
        EntityRepositoryInterface          $customerRepository,
        Customer                           $customerApiService,
        EventDispatcherInterface           $eventDispatcher,
        LoggerInterface                    $logger,
        SalesChannelContextPersister       $salesChannelContextPersister,
        EntityRepositoryInterface          $salutationRepository,
        SettingsService                    $settingsService,
        string                             $shopwareVersion,
        NumberRangeValueGeneratorInterface $valueGenerator
    )
    {
        $this->countryRepository = $countryRepository;
        $this->customerRepository = $customerRepository;
        $this->customerApiService = $customerApiService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
        $this->salutationRepository = $salutationRepository;
        $this->settingsService = $settingsService;
        $this->shopwareVersion = $shopwareVersion;
        $this->valueGenerator = $valueGenerator;
    }

    /**
     * Login the customer.
     *
     * @param CustomerEntity $customer
     * @param SalesChannelContext $context
     *
     * @return string|null
     */
    public function customerLogin(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        /** @var string|null $newToken */
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
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ]
            );
        } else if (version_compare($this->shopwareVersion, '6.3.4', '<')
            && version_compare($this->shopwareVersion, '6.3.3', '>=')) {
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
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function setCardToken(CustomerEntity $customer, string $cardToken, Context $context)
    {
        // Get existing custom fields
        $customFields = $customer->getCustomFields();

        // If custom fields are empty, create a new array
        if (!is_array($customFields)) {
            $customFields = [];
        }

        // Store the card token in the custom fields
        $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN] = $cardToken;

        $this->logger->debug("Setting Credit Card Token", [
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
     * Stores the custom fields.
     *
     * @param string $customerID
     * @param array $customFields
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function saveCustomerCustomFields(string $customerID, array $customFields, Context $context)
    {
        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customerID,
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * Stores the ideal issuer in the custom fields of the customer.
     *
     * @param CustomerEntity $customer
     * @param string $issuerId
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function setIDealIssuer(CustomerEntity $customer, string $issuerId, Context $context)
    {
        // Get existing custom fields
        $customFields = $customer->getCustomFields();

        // If custom fields are empty, create a new array
        if (!is_array($customFields)) {
            $customFields = [];
        }

        // Store the card token in the custom fields
        $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER] = $issuerId;

        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * @param string $customerId
     * @param string $salesChannelId
     * @param Context $context
     * @return string
     */
    public function getMollieCustomerId(string $customerId, string $salesChannelId, Context $context): string
    {
        $settings = $this->settingsService->getSettings($salesChannelId);
        $struct = $this->getCustomerStruct($customerId, $context);

        return $struct->getCustomerId($settings->getProfileId(), $settings->isTestMode());
    }

    /**
     * @param string $customerId
     * @param string $mollieCustomerId
     * @param string $profileId
     * @param bool $testMode
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     */
    public function setMollieCustomerId(string $customerId, string $mollieCustomerId, string $profileId, bool $testMode, Context $context)
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
     * @return CustomerEntity|null
     */
    public function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $customer = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $customerId));
            $criteria->addAssociations([
                'activeShippingAddress.country',
                'activeBillingAddress.country',
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
     * @return CustomerStruct
     * @throws CustomerCouldNotBeFoundException
     */
    public function getCustomerStruct(string $customerId, Context $context): CustomerStruct
    {
        $struct = new CustomerStruct();

        $customer = $this->getCustomer($customerId, $context);

        if (!($customer instanceof CustomerEntity)) {
            throw new CustomerCouldNotBeFoundException($customerId);
        }

        $customFields = $customer->getCustomFields() ?? [];

        // If there is a legacy customer id, set it separately
        if (isset($customFields[self::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID])) {
            $struct->setLegacyCustomerId($customFields[self::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID]);
        }

        // Then assign all custom fields under the mollie_payments key
        $struct->assign($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS] ?? []);

        return $struct;
    }

    /**
     * Return an array of address data.
     *
     * @param OrderAddressEntity | CustomerAddressEntity $address
     * @param CustomerEntity $customer
     * @return array
     */
    public function getAddressArray($address, CustomerEntity $customer)
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
     * @param string $paymentMethodId
     * @param SalesChannelContext $context
     * @return CustomerEntity|null
     */
    public function createApplePayDirectCustomer(string $firstname, string $lastname, string $email, string $phone, string $street, string $zipCode, string $city, string $countryISO2, string $paymentMethodId, SalesChannelContext $context)
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->getSalutationId($context->getContext());
        $countryId = $this->getCountryId($countryISO2, $context->getContext());

        $customerNumber = $this->valueGenerator->getValue(
            'customer',
            $context->getContext(),
            $context->getSalesChannelId()
        );

        $customer = [
            'id' => $customerId,
            'salutationId' => $salutationId,
            'firstName' => $firstname,
            'lastName' => $lastname,
            'customerNumber' => $customerNumber,
            'guest' => true,
            'email' => $email,
            'password' => Uuid::randomHex(),
            'defaultPaymentMethodId' => $paymentMethodId,
            'groupId' => $context->getSalesChannel()->getCustomerGroupId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $countryId,
                    'salutationId' => $salutationId,
                    'firstName' => $firstname,
                    'lastName' => $lastname,
                    'street' => $street,
                    'zipcode' => $zipCode,
                    'city' => $city,
                    'phoneNumber' => $phone,
                ],
            ],
        ];

        // Add the customer to the database
        $this->customerRepository->upsert([$customer], $context->getContext());

        return $this->getCustomer($customerId, $context->getContext());
    }

    /**
     * Returns a country id by its iso code.
     *
     * @param string $countryCode
     * @param Context $context
     *
     * @return string|null
     */
    public function getCountryId(string $countryCode, Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', strtoupper($countryCode)));

            // Get countries
            $countries = $this->countryRepository->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();

            return !empty($countries) ? $countries[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns a salutation id by its key.
     *
     * @param Context $context
     *
     * @return string|null
     */
    public function getSalutationId(Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salutationKey', 'not_specified'));

            // Get salutations
            $salutations = $this->salutationRepository->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();

            return !empty($salutations) ? $salutations[0] : null;
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
            $this->logger->warning('No profile ID available, cannot create customer.', [
                'saleschannel' => $salesChannelId,
                'customerId' => $customerId,
            ]);

            return;
        }

        if ($this->customerApiService->isLegacyCustomerValid($struct->getLegacyCustomerId(), $salesChannelId)) {
            $this->setMollieCustomerId(
                $customerId,
                $struct->getLegacyCustomerId(),
                $settings->getProfileId(),
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

        if (!empty($struct->getCustomerId($settings->getProfileId(), $settings->isTestMode()))) {
            return;
        }

        $customer = $this->getCustomer($customerId, $context);

        if (!($customer instanceof CustomerEntity)) {
            throw new CustomerCouldNotBeFoundException($customerId);
        }

        $mollieCustomer = $this->customerApiService->createCustomerAtMollie($customer);

        $this->setMollieCustomerId(
            $customerId,
            $mollieCustomer->id,
            $settings->getProfileId(),
            $settings->isTestMode(),
            $context
        );
    }
}
