<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\Order;

use Kiener\MolliePayments\Service\Transition\OrderTransitionService;
use Kiener\MolliePayments\Service\Transition\TransitionService;
use MolliePayments\Tests\Fakes\FakeEntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\LogEntryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderTransitionServiceTest extends TestCase
{
    /** @var FakeEntityRepository */
    private $loggerRepository;

    /** @var MockObject|StateMachineRegistry */
    private $stateMachineRegistry;

    /** @var OrderTransitionService */
    private $orderTransitionService;

    public function setUp(): void
    {
        $this->loggerRepository = new FakeEntityRepository(new LogEntryDefinition());
        $this->stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $this->transitionService = new TransitionService($this->stateMachineRegistry);
        $this->orderTransitionService = new OrderTransitionService($this->transitionService);
    }

    /**
     * Tests if the available transitions for the current state are returned
     */
    public function testGetAvailableTransitions(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName('transition_1');
        $transition2 = new StateMachineTransitionEntity();
        $transition2->setId('transitionId2');
        $transition2->setActionName('transition_2');

        $order = new OrderEntity();
        $order->setId('orderId');

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1, $transition2]);

        $availableTransitions = $this->orderTransitionService->getAvailableTransitions($order, $context);

        $expectedTransitions = ['transition_1', 'transition_2'];

        $this->assertEquals($expectedTransitions, $availableTransitions);
    }

    /**
     * Tests if the reopen transition is performed directly if the order
     * is in a state at which reopen is allowed (cancelled and completed)
     */
    public function testOpenOrderFromDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_REOPEN);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_COMPLETED);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_REOPEN,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with($expectedTransition, $context);

        $this->orderTransitionService->openOrder($order, $context);
    }

    /**
     * Tests if at first the complete and then the reopen transition
     * is performed if the order is in in_progress state at which a
     * reopen transition is not directly allowed
     */
    public function testOpenOrderFromNotDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_COMPLETE);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_IN_PROGRESS);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition1 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_COMPLETE,
            'stateId'
        );

        $expectedTransition2 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_REOPEN,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->exactly(2))
            ->method('transition')
            ->withConsecutive([$expectedTransition1, $context], [$expectedTransition2, $context]);

        $this->orderTransitionService->openOrder($order, $context);
    }

    /**
     * Tests if no transition is performed if the order is already in the
     * requested state
     */
    public function testOpenOrderDoesntPerformTransitionOnSameState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_PROCESS);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_OPEN);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->never())
            ->method('getAvailableTransitions');

        $this->stateMachineRegistry->expects($this->never())
            ->method('transition');

        $this->orderTransitionService->openOrder($order, $context);
    }

    /**
     * Tests if the process transition is performed directly if the order
     * is in open state at which the transition is allowed
     */
    public function testProcessOrderFromDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_PROCESS);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_OPEN);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_PROCESS,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with($expectedTransition, $context);

        $this->orderTransitionService->processOrder($order, $context);
    }

    /**
     * Tests if at first the reopen and then the process transition
     * is performed if the order is in completed state at which a
     * process transition is not directly allowed
     */
    public function testProcessOrderFromNotDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_REOPEN);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_COMPLETED);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition1 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_REOPEN,
            'stateId'
        );

        $expectedTransition2 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_PROCESS,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->exactly(2))
            ->method('transition')
            ->withConsecutive([$expectedTransition1, $context], [$expectedTransition2, $context]);

        $this->orderTransitionService->processOrder($order, $context);
    }

    /**
     * Tests if no transition is performed if the order is already in the
     * requested state
     */
    public function testProcessOrderDoesntPerformTransitionOnSameState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_COMPLETE);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_IN_PROGRESS);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->never())
            ->method('getAvailableTransitions');

        $this->stateMachineRegistry->expects($this->never())
            ->method('transition');

        $this->orderTransitionService->processOrder($order, $context);
    }

    /**
     * Tests if the complete transition is performed directly if the order
     * is in in_progress state at which the transition is allowed
     */
    public function testCompleteOrderFromDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_COMPLETE);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_IN_PROGRESS);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_COMPLETE,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with($expectedTransition, $context);

        $this->orderTransitionService->completeOrder($order, $context);
    }

    /**
     * Tests if at first the process and then the complete transition
     * is performed if the order is in open state at which a
     * complete transition is not directly allowed
     */
    public function testCompleteOrderFromOpen(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_PROCESS);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_OPEN);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->exactly(2))
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturnOnConsecutiveCalls([$transition1], [$transition1]);

        $expectedTransition1 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_PROCESS,
            'stateId'
        );

        $expectedTransition2 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_COMPLETE,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->exactly(2))
            ->method('transition')
            ->withConsecutive([$expectedTransition1, $context], [$expectedTransition2, $context]);

        $this->orderTransitionService->completeOrder($order, $context);
    }

    /**
     * Tests if at first the reopen and then the complete transition
     * is performed if the order is in cancelled state at which a
     * complete transition is not directly allowed
     */
    public function testCompleteOrderFromCancelled(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_REOPEN);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_CANCELLED);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->exactly(2))
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturnOnConsecutiveCalls([$transition1], [$transition1]);

        $expectedTransition1 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_REOPEN,
            'stateId'
        );

        $expectedTransition2 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_PROCESS,
            'stateId'
        );

        $expectedTransition3 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_COMPLETE,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->exactly(3))
            ->method('transition')
            ->withConsecutive([$expectedTransition1, $context], [$expectedTransition2, $context], [$expectedTransition3, $context]);

        $this->orderTransitionService->completeOrder($order, $context);
    }

    /**
     * Tests if no transition is performed if the order is already in the
     * requested state
     */
    public function testCompleteOrderDoesntPerformTransitionOnSameState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_COMPLETE);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_COMPLETED);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->never())
            ->method('getAvailableTransitions');

        $this->stateMachineRegistry->expects($this->never())
            ->method('transition');

        $this->orderTransitionService->completeOrder($order, $context);
    }

    /**
     * Tests if the cancel transition is performed directly if the order
     * is in open state at which the transition is allowed
     */
    public function testCancelOrderFromDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_CANCEL);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_OPEN);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_CANCEL,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with($expectedTransition, $context);

        $this->orderTransitionService->cancelOrder($order, $context);
    }

    /**
     * Tests if at first the reopen and then the cancel transition
     * is performed if the order is in completed state at which a
     * cancel transition is not directly allowed
     */
    public function testCancelOrderFromNotDirectlyAllowedState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_REOPEN);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_COMPLETED);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDefinition::ENTITY_NAME, 'orderId', 'stateId', $context)
            ->willReturn([$transition1]);

        $expectedTransition1 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_REOPEN,
            'stateId'
        );

        $expectedTransition2 = new Transition(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            StateMachineTransitionActions::ACTION_CANCEL,
            'stateId'
        );

        $this->stateMachineRegistry->expects($this->exactly(2))
            ->method('transition')
            ->withConsecutive([$expectedTransition1, $context], [$expectedTransition2, $context]);

        $this->orderTransitionService->cancelOrder($order, $context);
    }

    /**
     * Tests if no transition is performed if the order is already in the
     * requested state
     */
    public function testCancelOrderDoesntPerformTransitionOnSameState(): void
    {
        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName(StateMachineTransitionActions::ACTION_REOPEN);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setName(OrderStates::STATE_CANCELLED);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->never())
            ->method('getAvailableTransitions');

        $this->stateMachineRegistry->expects($this->never())
            ->method('transition');

        $this->orderTransitionService->cancelOrder($order, $context);
    }
}
