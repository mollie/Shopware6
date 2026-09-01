<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Return;

use Mollie\Shopware\Component\Refund\Return\OrderReturnSubscriber;
use Mollie\Shopware\Unit\Refund\Return\Fake\FakeCancelAction;
use Mollie\Shopware\Unit\Refund\Return\Fake\FakeRefundAction;
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

    public function testWrittenOnLiveVersionTriggersTheRefundAction(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $subscriber->onOrderReturnWritten($this->insertEvent('return-id', Context::createDefaultContext()));

        $this->assertSame(['return-id'], $refundAction->executeOnCreateCalls);
    }

    public function testWrittenOnNonLiveVersionDoesNotTriggerTheRefundAction(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        // Opening an order in the admin clones its returns into a non-live version (issue #1421).
        $context = Context::createDefaultContext()->createWithVersionId('0198a1b2c3d4e5f60718293a4b5c6d7e');

        $subscriber->onOrderReturnWritten($this->insertEvent('return-id', $context));

        $this->assertSame([], $refundAction->executeOnCreateCalls);
    }

    /**
     * Only a newly created return can already be done. An update would otherwise refund a second
     * time on every edit of the return.
     */
    public function testAnUpdatedReturnDoesNotTriggerAnotherRefund(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $writeResult = new EntityWriteResult('return-id', [], 'order_return', EntityWriteResult::OPERATION_UPDATE);

        $subscriber->onOrderReturnWritten(new EntityWrittenEvent('order_return', [$writeResult], Context::createDefaultContext()));

        $this->assertSame([], $refundAction->executeOnCreateCalls);
    }

    public function testAReturnSetToDoneIsRefunded(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('done'));

        $this->assertSame(['return-id'], $refundAction->executeCalls);
    }

    public function testACancelledReturnIsCancelled(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('cancelled'));

        $this->assertSame(['return-id'], $cancelAction->executeCalls);
    }

    /**
     * A return that only moves to "in progress" has nothing to refund yet.
     */
    public function testAnyOtherReturnStateIsIgnored(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('in_progress'));

        $this->assertSame([], $refundAction->executeCalls);
        $this->assertSame([], $cancelAction->executeCalls);
    }

    /**
     * Shopware fires the change twice, once for leaving the old state and once for entering the
     * new one. Acting on both would refund twice.
     */
    public function testLeavingAStateDoesNotRefund(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('done', StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE));

        $this->assertSame([], $refundAction->executeCalls);
    }

    /**
     * The Return Management completes a return inside the working version of the order the admin has
     * open, so a live context never arrives here. Skipping those meant no return was ever refunded.
     */
    public function testAStateChangeOnANonLiveVersionRefunds(): void
    {
        $refundAction = new FakeRefundAction();
        $cancelAction = new FakeCancelAction();
        $subscriber = new OrderReturnSubscriber($refundAction, $cancelAction);

        $context = Context::createDefaultContext()->createWithVersionId('0198a1b2c3d4e5f60718293a4b5c6d7e');

        $subscriber->onOrderReturnStateChanged($this->stateChangeEvent('done', context: $context));

        $this->assertSame(['return-id'], $refundAction->executeCalls);
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
