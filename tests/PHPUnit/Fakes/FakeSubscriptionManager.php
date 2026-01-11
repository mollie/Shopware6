<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManagerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeSubscriptionManager implements SubscriptionManagerInterface
{
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        return 'sub_phpunit_123';
    }

    public function confirmSubscription(OrderEntity $order, string $mandateId, Context $context): void
    {
    }
}
