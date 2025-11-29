<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEvents;
use PHPUnit\Framework\TestCase;

class SubscriptionEventsTest extends TestCase
{
    /**
     * This test verifies that our loaded event
     * name is not accidentally changed
     */
    public function testLoadedEvent(): void
    {
        static::assertSame('mollie_subscription.loaded', SubscriptionEvents::SUBSCRIPTIONS_LOADED_EVENT);
    }
}
