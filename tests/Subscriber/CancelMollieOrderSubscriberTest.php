<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Subscriber\CancelMollieOrderSubscriber;
use Mollie\Api\Types\OrderStatus;
use PHPUnit\Framework\TestCase;

class CancelMollieOrderSubscriberTest extends TestCase
{

    public function testListeningOnCorrectEvent(): void
    {
        self::assertArrayHasKey('state_machine.order.state_changed', CancelMollieOrderSubscriber::getSubscribedEvents());
    }

    public function testCancelMollieOrderStatesConstant(): void
    {
        $expected = [
            OrderStatus::STATUS_CREATED,
            OrderStatus::STATUS_AUTHORIZED,
            OrderStatus::STATUS_SHIPPING
        ];

        self::assertSame($expected, CancelMollieOrderSubscriber::MOLLIE_CANCEL_ORDER_STATES);
    }
}
