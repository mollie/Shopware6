<?php

namespace Kiener\MolliePayments\Gateway;


use Kiener\MolliePayments\Gateway\Mollie\Model\SubscriptionDefinitionInterface;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Subscription;
use Mollie\Api\Resources\SubscriptionCollection;


interface MollieGatewayInterface
{

    /**
     * @param string $salesChannelID
     */
    public function switchClient(string $salesChannelID): void;

    /**
     * @return string
     */
    public function getOrganizationId(): string;

    /**
     * @return string
     */
    public function getProfileId(): string;

    /**
     * @param string $orderId
     * @return Order
     */
    public function getOrder(string $orderId): Order;

    /**
     * @param string $customerID
     * @param array<mixed> $data
     * @return Subscription
     */
    public function createSubscription(string $customerID, array $data): Subscription;

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, string $customerId): void;

    /**
     * @return SubscriptionCollection
     */
    public function getAllSubscriptions(): SubscriptionCollection;

}
