<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SubscriptionManagerInterface
{
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string;

    public function confirmSubscription(OrderEntity $order, string $mandateId, Context $context): void;
}
