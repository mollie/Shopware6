<?php

namespace Kiener\MolliePayments\Gateway;

use Kiener\MolliePayments\Gateway\Mollie\Model\Issuer;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Subscription;

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
     * @return Issuer[]
     */
    public function getIDealIssuers(): array;

    /**
     * @param string $orderId
     * @return Order
     */
    public function getOrder(string $orderId): Order;

    /**
     * @param string $paymentId
     * @return Payment
     */
    public function getPayment(string $paymentId): Payment;

    /**
     * @param array<mixed> $data
     * @return Payment
     */
    public function createPayment(array $data): Payment;

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
     * @param string $subscriptionId
     * @param string $customerId
     * @param string $mandateId
     */
    public function updateSubscription(string $subscriptionId, string $customerId, string $mandateId): void;

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @return Subscription
     */
    public function getSubscription(string $subscriptionId, string $customerId): Subscription;
}
