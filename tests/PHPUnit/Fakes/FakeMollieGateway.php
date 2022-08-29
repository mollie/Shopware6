<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Gateway\Mollie\Model\Issuer;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Subscription;


class FakeMollieGateway implements MollieGatewayInterface
{

    public function switchClient(string $salesChannelID): void
    {
        // TODO: Implement switchClient() method.
    }

    public function getOrganizationId(): string
    {
        // TODO: Implement getOrganizationId() method.
    }

    public function getProfileId(): string
    {
        // TODO: Implement getProfileId() method.
    }

    public function getOrder(string $orderId): Order
    {
        // TODO: Implement getOrder() method.
    }

    public function getPayment(string $paymentId): Payment
    {
        // TODO: Implement getPayment() method.
    }

    public function createSubscription(string $customerID, array $data): Subscription
    {
        // TODO: Implement createSubscription() method.
    }

    public function cancelSubscription(string $subscriptionId, string $customerId): void
    {
        // TODO: Implement cancelSubscription() method.
    }

    public function getSubscription(string $subscriptionId, string $customerId): Subscription
    {
        // TODO: Implement getSubscription() method.
    }

    public function createPayment(array $data): Payment
    {
        // TODO: Implement createPayment() method.
    }

    public function updateSubscription(string $subscriptionId, string $customerId, string $mandateId): void
    {
        // TODO: Implement updateSubscription() method.
    }

    public function getIDealIssuers(): array
    {
        return [];
    }

}
