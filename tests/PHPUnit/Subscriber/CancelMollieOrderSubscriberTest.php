<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Subscriber;

use Kiener\MolliePayments\Subscriber\CancelOrderSubscriber;
use Mollie\Api\Types\OrderStatus;
use PHPUnit\Framework\TestCase;

class CancelMollieOrderSubscriberTest extends TestCase
{
    public function testListeningOnCorrectEvent(): void
    {
        self::assertArrayHasKey('state_machine.order.state_changed', CancelOrderSubscriber::getSubscribedEvents());
    }

    public function testCancelMollieOrderStatesConstant(): void
    {
        $expected = [
            OrderStatus::STATUS_CREATED,
            OrderStatus::STATUS_AUTHORIZED,
            OrderStatus::STATUS_SHIPPING,
        ];

        self::assertSame($expected, CancelOrderSubscriber::ALLOWED_CANCELLABLE_MOLLIE_STATES);
    }
}
