<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Subscriber\OrderReturnSubscriber;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(OrderReturnSubscriber::class)]
class OrderReturnSubscriberTest extends TestCase
{
    /**
     * This test verifies that the subscriber listens to the raw state machine
     * event for order_return, which carries the returnId via getTransition().
     * It must NOT listen to OrderStateMachineStateChangeEvent which only has the order.
     */
    public function testListeningOnCorrectEvent(): void
    {
        $events = OrderReturnSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('state_machine.order_return.state_changed', $events);
    }

    /**
     * This test verifies the subscriber does NOT subscribe to the old
     * order-level state change events which caused the wrong return to be selected.
     */
    public function testDoesNotListenToOldOrderStateEvents(): void
    {
        $events = OrderReturnSubscriber::getSubscribedEvents();

        self::assertArrayNotHasKey('state_enter.order_return.state.done', $events);
        self::assertArrayNotHasKey('state_enter.order_return.state.cancelled', $events);
    }
}
