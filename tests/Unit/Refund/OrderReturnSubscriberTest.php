<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Refund\OrderReturnSubscriber;
use Mollie\Shopware\Unit\Refund\Fake\FakeOrderReturnHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

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

    public function testWrittenOnLiveVersionTriggersHandler(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $subscriber->onOrderReturnWritten($this->insertEvent('return-id', Context::createDefaultContext()));

        $this->assertSame(['return-id'], $handler->returnOnCreatedAsDoneCalls);
    }

    public function testWrittenOnNonLiveVersionDoesNotTriggerHandler(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        // Opening an order in the admin clones its returns into a non-live version (issue #1421).
        $context = Context::createDefaultContext()->createWithVersionId('0198a1b2c3d4e5f60718293a4b5c6d7e');

        $subscriber->onOrderReturnWritten($this->insertEvent('return-id', $context));

        $this->assertSame([], $handler->returnOnCreatedAsDoneCalls);
    }

    private function insertEvent(string $returnId, Context $context): EntityWrittenEvent
    {
        $writeResult = new EntityWriteResult(
            $returnId,
            [],
            'order_return',
            EntityWriteResult::OPERATION_INSERT
        );

        return new EntityWrittenEvent('order_return', [$writeResult], $context);
    }
}
