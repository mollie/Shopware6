<?php

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Resources\Order;
use Mollie\Api\Types\PaymentStatus;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
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

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var LoggerInterface */
    private $logger;

    /** @var SalesChannelContextPersister */
    private $salesChannelContextPersister;

    /** @var EntityRepositoryInterface */
    private $salutationRepository;

    /**
     * Creates a new instance of the customer service.
     *
     * @param EntityRepositoryInterface $countryRepository
     * @param EntityRepositoryInterface $customerRepository
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param SalesChannelContextPersister $salesChannelContextPersister
     * @param EntityRepositoryInterface $salutationRepository
     */
    public function __construct(
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        SalesChannelContextPersister $salesChannelContextPersister,
        EntityRepositoryInterface $salutationRepository
    )
    {
        $this->countryRepository = $countryRepository;
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
        $this->salutationRepository = $salutationRepository;
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
        $newToken = $this->salesChannelContextPersister->replace($context->getToken());

        // Persist the new token
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

        /** @var CustomerLoginEvent $event */
        $event = new CustomerLoginEvent($context, $customer, $newToken);

        // Dispatch the customer login event
        $this->eventDispatcher->dispatch($event);

        return $newToken;
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

        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * Stores the custom fields.
     *
     * @param CustomerEntity $customer
     * @param array $customFields
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function saveCustomerCustomFields(CustomerEntity $customer, array $customFields, Context $context)
    {
        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customer->getId(),
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
            $criteria->addAssociation('activeShippingAddress');
            $criteria->addAssociation('activeBillingAddress');
            $criteria->addAssociation('defaultShippingAddress');
            $criteria->addAssociation('defaultBillingAddress');
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
        }

        return $customer;
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
     * Returns a customer for a given array of customer data.
     *
     * @param array $customerData
     * @param PaymentMethodEntity $paymentMethod
     * @param SalesChannelContext $context
     *
     * @return CustomerEntity|null
     */
    public function createCustomerFromData(array $customerData, ?PaymentMethodEntity $paymentMethod, SalesChannelContext $context)
    {
        /** @var string $customerId */
        $customerId = Uuid::randomHex();

        /** @var string $addressId */
        $addressId = Uuid::randomHex();

        // Apple Pay Direct variables
        $countryId = null;
        $emailAddress = null;
        $familyName = null;
        $givenName = null;
        $locality = null;
        $phoneNumber = null;
        $postalCode = null;
        $salutationId = $this->getSalutationId($context->getContext());
        $street = null;

        // Get the country based on the country code
        if (isset($customerData['countryCode'])) {
            $countryId = $this->getCountryId($customerData['countryCode'], $context->getContext());
        }

        // Get the e-mail address
        if (isset($customerData['emailAddress'])) {
            $emailAddress = $customerData['emailAddress'];
        }

        // Get the family name
        if (isset($customerData['familyName'])) {
            $familyName = $customerData['familyName'];
        }

        // Get the given name
        if (isset($customerData['givenName'])) {
            $givenName = $customerData['givenName'];
        }

        // Get the locality
        if (isset($customerData['locality'])) {
            $locality = $customerData['locality'];
        }

        // Get the phone number
        if (isset($customerData['phoneNumber'])) {
            $phoneNumber = $customerData['phoneNumber'];
        }

        // Get the postal code
        if (isset($customerData['postalCode'])) {
            $postalCode = $customerData['postalCode'];
        }

        // Get the street from the address lines
        if (isset($customerData['addressLines'])) {
            $street = implode(', ', $customerData['addressLines']);
        }

        // Create a new customer
        if (
            (string)$countryId !== ''
            && $emailAddress !== null
            && $familyName !== null
            && $givenName !== null
            && $locality !== null
            && $postalCode !== null
            && (string)$salutationId !== ''
            && $street !== null
            && !is_null($paymentMethod)
        ) {
            $customer = [
                'id' => $customerId,
                'salutationId' => $salutationId,
                'firstName' => $givenName,
                'lastName' => $familyName,
                'customerNumber' => $paymentMethod->getName() . '.Express-Checkout.' . date('YmdHis'),
                'guest' => true,
                'email' => $emailAddress,
                'password' => Uuid::randomHex(),
                'defaultPaymentMethodId' => $paymentMethod->getUniqueIdentifier(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'salesChannelId' => $context->getSalesChannel()->getId(),
                'defaultBillingAddressId' => $addressId,
                'defaultShippingAddressId' => $addressId,
                'addresses' => [
                    [
                        'id' => $addressId,
                        'customerId' => $customerId,
                        'countryId' => $countryId,
                        'salutationId' => $salutationId,
                        'firstName' => $givenName,
                        'lastName' => $familyName,
                        'street' => $street,
                        'zipcode' => $postalCode,
                        'city' => $locality,
                        'phoneNumber' => $phoneNumber,
                    ],
                ],
            ];

            // Add the customer to the database
            $this->customerRepository->upsert([$customer], $context->getContext());

            return $this->getCustomer($customerId, $context->getContext());
        }

        return null;
    }

    /**
     * Returns a country id by it's iso code.
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
     * Returns a salutation id by it's key.
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

    public function updateCustomerWithMollieData(
        Order $mollieOrder,
        string $customerId,
        MollieSettingStruct $settings,
        Context $context
    )
    {
        $customer = $this->getCustomer($customerId, $context);

        if (!isset($mollieOrder->_embedded->payments)) {
            return;
        }

        $paymentDetails = null;

        foreach ($mollieOrder->_embedded->payments as $payment) {
            if ($payment->status === PaymentStatus::STATUS_PAID) {
                $paymentDetails = $payment->details;
                break;
            }
        }

        if (is_null($paymentDetails)) {
            return;
        }

        if (!isset($paymentDetails->shippingAddress)) {
            if ($settings->isTestMode()) {
                $paymentDetails->shippingAddress = new \stdClass();
                $paymentDetails->shippingAddress->streetAndNumber = "Express Checkout Test Address";
                $paymentDetails->shippingAddress->postalCode = "Express Checkout Test Zipcode";
                $paymentDetails->shippingAddress->city = "Express Checkout Test City";
                $paymentDetails->shippingAddress->country = "NL";
            } else {
                throw new \Exception("No shipping address provided by the payment processor");
            }
        }

        list($firstName, $lastName) = explode(' ', $paymentDetails->consumerName, 2);
        $email = $paymentDetails->consumerAccount;

        $countryId = $this->countryRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('iso', $paymentDetails->shippingAddress->country)),
            $context
        )->firstId();

        $billingAddress = [
            'id' => $customer->getDefaultBillingAddress()->getUniqueIdentifier(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'street' => $paymentDetails->shippingAddress->streetAndNumber,
            'zipcode' => $paymentDetails->shippingAddress->postalCode,
            'city' => $paymentDetails->shippingAddress->city,
            'countryId' => $countryId
        ];

        $shippingAddress = [
            'id' => $customer->getDefaultShippingAddress()->getUniqueIdentifier(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'street' => $paymentDetails->shippingAddress->streetAndNumber,
            'zipcode' => $paymentDetails->shippingAddress->postalCode,
            'city' => $paymentDetails->shippingAddress->city,
            'countryId' => $countryId
        ];

        $customerUpdate = [
            'id' => $customer->getUniqueIdentifier(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'defaultBillingAddress' => $billingAddress,
            'defaultShippingAddress' => $shippingAddress,
            'activeBillingAddress' => $billingAddress,
            'activeShippingAddress' => $shippingAddress,
        ];

        $this->customerRepository->update([$customerUpdate], $context);


    }
}
