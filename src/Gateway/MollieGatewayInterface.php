<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Gateway;

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Subscription;
use Mollie\Api\Resources\Terminal;

interface MollieGatewayInterface
{
    public function switchClient(string $salesChannelID): void;

    public function getOrganizationId(): string;

    public function getProfileId(): string;

    /**
     * @return Terminal[]
     */
    public function getPosTerminals(): array;

    public function getOrder(string $orderId): Order;

    public function getPayment(string $paymentId): Payment;

    /**
     * @param array<mixed> $data
     */
    public function createPayment(array $data): Payment;

    /**
     * @param array<mixed> $data
     */
    public function createSubscription(string $customerID, array $data): Subscription;

    public function cancelSubscription(string $subscriptionId, string $customerId): void;

    public function updateSubscription(string $subscriptionId, string $customerId, string $mandateId): void;

    public function getSubscription(string $subscriptionId, string $customerId): Subscription;
}
