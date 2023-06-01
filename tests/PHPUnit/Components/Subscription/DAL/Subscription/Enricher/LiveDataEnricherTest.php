<?php

namespace MolliePayments\Tests\Components\Subscription\DAL\Subscription\Enricher;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Enricher\LiveDataEnricher;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEvents;
use PHPUnit\Framework\TestCase;

class LiveDataEnricherTest extends TestCase
{
    /**
     * This test verifies that our correct list of
     * events is used to subscribe.
     *
     * @return void
     */
    public function testSubscribedEvents(): void
    {
        $expected = [
            SubscriptionEvents::SUBSCRIPTIONS_LOADED_EVENT,
        ];

        static::assertSame($expected, array_keys(LiveDataEnricher::getSubscribedEvents()));
    }
}
