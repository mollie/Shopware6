<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Subscription\DAL\Subscription\Enricher;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Enricher\LiveDataEnricher;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEvents;
use PHPUnit\Framework\TestCase;

class LiveDataEnricherTest extends TestCase
{
    /**
     * This test verifies that our correct list of
     * events is used to subscribe.
     */
    public function testSubscribedEvents(): void
    {
        $expected = [
            SubscriptionEvents::SUBSCRIPTIONS_LOADED_EVENT,
        ];

        static::assertSame($expected, array_keys(LiveDataEnricher::getSubscribedEvents()));
    }
}
