<?php

namespace Kiener\MolliePayments\Gateway\Mollie;


use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\Mollie\Model\SubscriptionDefinitionInterface;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Profile;
use Mollie\Api\Resources\Subscription;
use Mollie\Api\Resources\SubscriptionCollection;


class MollieGateway implements MollieGatewayInterface
{

    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var MollieApiFactory
     */
    private $factory;


    /**
     * @param MollieApiFactory $clientFactory
     */
    public function __construct(MollieApiFactory $clientFactory)
    {
        $this->factory = $clientFactory;
    }


    /**
     * @param string $salesChannelID
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function switchClient(string $salesChannelID): void
    {
        $this->apiClient = $this->factory->getClient($salesChannelID);
    }

    /**
     * @return string
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getProfileId(): string
    {
        $profile = $this->apiClient->profiles->get('me');

        if (!$profile instanceof Profile) {
            return '';
        }

        return (string)$profile->id;
    }

    /**
     * @return string
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getOrganizationId(): string
    {
        $profile = $this->apiClient->profiles->get('me');

        if (!$profile instanceof Profile) {
            return '';
        }

        # the organization is in a full dashboard URL
        # so we grab it, and extract that slug with the organization id
        $orgId = (string)$profile->_links->dashboard->href;

        $parts = explode('/', $orgId);

        foreach ($parts as $part) {
            if (strpos($part, 'org_') === 0) {
                $orgId = $part;
                break;
            }
        }

        return (string)$orgId;
    }

    /**
     * @param string $orderId
     * @return Order
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getOrder(string $orderId): Order
    {
        $order = $this->apiClient->orders->get(
            $orderId,
            [
                'embed' => 'payments',
            ]
        );

        return $order;
    }

    /**
     * @param string $paymentId
     * @return Payment
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getPayment(string $paymentId): Payment
    {
        return $this->apiClient->payments->get($paymentId);
    }

    /**
     * @param string $customerID
     * @param array<mixed> $data
     * @return Subscription
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createSubscription(string $customerID, array $data): Subscription
    {
        return $this->apiClient->subscriptions->createForId($customerID, $data);
    }

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @return void
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function cancelSubscription(string $subscriptionId, string $customerId): void
    {
        $this->apiClient->subscriptions->cancelForId(
            $customerId,
            $subscriptionId
        );
    }

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @return Subscription
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getSubscription(string $subscriptionId, string $customerId): Subscription
    {
        return $this->apiClient->subscriptions->getForId($customerId, $subscriptionId);
    }

}
