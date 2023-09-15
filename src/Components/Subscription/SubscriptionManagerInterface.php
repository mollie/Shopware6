<?php

namespace Kiener\MolliePayments\Components\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SubscriptionManagerInterface
{
    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string;

    /**
     * @param OrderEntity $order
     * @param string $mandateId
     * @param Context $context
     * @return void
     */
    public function confirmSubscription(OrderEntity $order, string $mandateId, Context $context): void;
}
