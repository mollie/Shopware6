<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerMandatesException;
use Kiener\MolliePayments\Exception\CouldNotRevokeMollieCustomerMandateException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\BaseCollection;
use Mollie\Api\Resources\MandateCollection;

class Mandate
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
     * @throws CouldNotFetchMollieCustomerMandatesException
     * @return BaseCollection|MandateCollection
     */
    public function getMandatesByMollieCustomerId(string $customerId, string $salesChannelId)
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            return $apiClient->mandates->listForId($customerId);
        } catch (ApiException $e) {
            throw new CouldNotFetchMollieCustomerMandatesException($customerId, $salesChannelId, $e);
        }
    }

    /**
     * @param string $customerId
     * @param string $mandateId
     * @param string $salesChannelId
     * @throws CouldNotRevokeMollieCustomerMandateException
     * @return void
     */
    public function revokeMandateByMollieCustomerId(string $customerId, string $mandateId, string $salesChannelId): void
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            $apiClient->mandates->revokeForId($customerId, $mandateId);
        } catch (ApiException $e) {
            throw new CouldNotRevokeMollieCustomerMandateException($mandateId, $customerId, $salesChannelId, $e);
        }
    }
}
