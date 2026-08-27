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
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;

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

    /**
     * Only a newly created return can already be done. An update would otherwise refund a second
     * time on every edit of the return.
     */
    public function testAnUpdatedReturnDoesNotTriggerAnotherRefund(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $writeResult = new EntityWriteResult('return-id', [], 'order_return', EntityWriteResult::OPERATION_UPDATE);

        $subscriber->onOrderReturnWritten(new EntityWrittenEvent('order_return', [$writeResult], Context::createDefaultContext()));

        $this->assertSame([], $handler->returnOnCreatedAsDoneCalls);
    }

    public function testAReturnSetToDoneIsRefunded(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('done'));

        $this->assertSame(['return-id'], $handler->returnCalls);
    }

    public function testACancelledReturnIsCancelled(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('cancelled'));

        $this->assertSame(['return-id'], $handler->cancelCalls);
    }

    /**
     * A return that only moves to "in progress" has nothing to refund yet.
     */
    public function testAnyOtherReturnStateIsIgnored(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('in_progress'));

        $this->assertSame([], $handler->returnCalls);
        $this->assertSame([], $handler->cancelCalls);
    }

    /**
     * Shopware fires the change twice, once for leaving the old state and once for entering the
     * new one. Acting on both would refund twice.
     */
    public function testLeavingAStateDoesNotRefund(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('done', StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE));

        $this->assertSame([], $handler->returnCalls);
    }

    /**
     * The Return Management completes a return inside the working version of the order the admin has
     * open, so a live context never arrives here. Skipping those meant no return was ever refunded.
     */
    public function testAStateChangeOnANonLiveVersionRefunds(): void
    {
        $handler = new FakeOrderReturnHandler();
        $subscriber = new OrderReturnSubscriber($handler);

        $context = Context::createDefaultContext()->createWithVersionId('0198a1b2c3d4e5f60718293a4b5c6d7e');

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('done', context: $context));

        $this->assertSame(['return-id'], $handler->returnCalls);
    }

    private function stateChangeEvent(
        string $stateName,
        string $transitionSide = StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
        ?Context $context = null,
    ): StateMachineStateChangeEvent {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId('state-machine-id');
        $stateMachine->setTechnicalName('order_return.state');

        $previousState = new StateMachineStateEntity();
        $previousState->setId('previous-state-id');
        $previousState->setTechnicalName('open');

        $nextState = new StateMachineStateEntity();
        $nextState->setId('next-state-id');
        $nextState->setTechnicalName($stateName);

        return new StateMachineStateChangeEvent(
            $context ?? Context::createDefaultContext(),
            $transitionSide,
            new Transition('order_return', 'return-id', $stateName, 'stateId'),
            $stateMachine,
            $previousState,
            $nextState
        );
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
