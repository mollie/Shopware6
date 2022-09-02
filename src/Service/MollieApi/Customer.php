<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Customer as MollieCustomer;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class Customer
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    public function __construct(MollieApiFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * @param string $customerId
     * @param string $salesChannelId
     * @throws CouldNotFetchMollieCustomerException
     * @return MollieCustomer
     */
    public function getMollieCustomerById(string $customerId, string $salesChannelId): MollieCustomer
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            return $apiClient->customers->get($customerId);
        } catch (ApiException $e) {
            throw new CouldNotFetchMollieCustomerException($customerId, $salesChannelId, $e);
        }
    }

    /**
     * @param CustomerEntity $customer
     * @throws CouldNotCreateMollieCustomerException
     * @return MollieCustomer
     */
    public function createCustomerAtMollie(CustomerEntity $customer): MollieCustomer
    {
        try {
            $apiClient = $this->clientFactory->getClient($customer->getSalesChannelId());

            return $apiClient->customers->create([
                'name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                'email' => $customer->getEmail(),
                'metadata' => [
                    'id' => $customer->getId()
                ]
            ]);
        } catch (ApiException $e) {
            throw new CouldNotCreateMollieCustomerException(
                $customer->getCustomerNumber(),
                $customer->getFirstName() . ' ' . $customer->getLastName(),
                $e
            );
        }
    }

    public function isLegacyCustomerValid(?string $legacyCustomerId, string $salesChannelId): bool
    {
        if (empty($legacyCustomerId)) {
            return false;
        }

        try {
            $mollieCustomer = $this->getMollieCustomerById(
                $legacyCustomerId,
                $salesChannelId
            );

            return $mollieCustomer instanceof MollieCustomer;
        } catch (CouldNotFetchMollieCustomerException $e) {
            return false;
        }
    }
}
