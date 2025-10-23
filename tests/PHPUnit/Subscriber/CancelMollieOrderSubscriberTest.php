<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Subscriber\CancelOrderSubscriber;
use Mollie\Api\Types\PaymentStatus;
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
            PaymentStatus::OPEN,
            PaymentStatus::AUTHORIZED
        ];

        self::assertSame($expected, CancelOrderSubscriber::ALLOWED_CANCELLABLE_MOLLIE_STATES);
    }
}
