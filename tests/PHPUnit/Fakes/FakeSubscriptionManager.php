<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManagerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeSubscriptionManager implements SubscriptionManagerInterface
{

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        return "sub_phpunit_123";
    }

}
