<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Subscription;

interface SubscriptionGatewayInterface
{
    public function createSubscription(CreateSubscription $createSubscription,string $customerId,string $orderNumber, string $salesChannelId): Subscription;

    public function getSubscription(string $mollieSubscriptionId,  string $customerId, string $orderNumber, string $salesChannelId):Subscription;

    public function updateSubscription(array $data, string $mollieSubscriptionId, string $customerId, string $orderNumber, string $salesChannelId):Subscription;
}
