<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionEvents::class)]
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
