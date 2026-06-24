<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Refund\OrderReturnSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderReturnSubscriber::class)]
final class OrderReturnSubscriberTest extends TestCase
{
    public function testListeningOnCorrectEvent(): void
    {
        $events = OrderReturnSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('state_machine.order_return.state_changed', $events);
    }

    public function testListeningOnOrderReturnWritten(): void
    {
        $events = OrderReturnSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('order_return.written', $events);
    }

    public function testDoesNotListenToOldOrderStateEvents(): void
    {
        $events = OrderReturnSubscriber::getSubscribedEvents();

        $this->assertArrayNotHasKey('state_enter.order_return.state.done', $events);
        $this->assertArrayNotHasKey('state_enter.order_return.state.cancelled', $events);
    }
}
