<?php

namespace Kiener\MolliePayments\Components\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SubscriptionManagerInterface
{

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string;

}
