<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionCollection;

interface SubscriptionGatewayInterface
{
    public function createSubscription(CreateSubscription $createSubscription,string $customerId,string $orderNumber, string $salesChannelId): Subscription;

    public function copySubscription(Subscription $mollieSubscription,string $customerId,string $orderNumber, string $salesChannelId): Subscription;

    public function getSubscription(string $mollieSubscriptionId,  string $customerId, string $orderNumber, string $salesChannelId): Subscription;

    public function cancelSubscription(string $mollieSubscriptionId, string $customerId, string $orderNumber, string $salesChannelId): Subscription;

    public function updateSubscription(Subscription $mollieSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription;

    /**
     * Lists subscriptions for the merchant profile, sorted by Mollie ID ascending.
     * Pass `from` to start the page at a specific subscription ID (cursor pagination).
     * Maximum `limit` accepted by Mollie is 250.
     */
    public function listSubscriptions(?string $from, int $limit, string $salesChannelId): SubscriptionCollection;
}
