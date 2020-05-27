<?php

namespace Kiener\MolliePayments\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CustomerService
{
    /** @var EntityRepository */
    protected $customerRepository;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        EntityRepository $customerRepository,
        LoggerInterface $logger
    )
    {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Stores the credit card token in the custom fields of the customer.
     *
     * @param CustomerEntity $customer
     * @param string $cardToken
     * @param Context $context
     *
     * @return \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent
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
        $customFields['mollie_payments']['credit_card_token'] = $cardToken;

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
    public function getCustomer(string $customerId, Context $context) : ?CustomerEntity
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
}