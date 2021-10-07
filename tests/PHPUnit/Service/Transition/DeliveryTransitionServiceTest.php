<?php

namespace Kiener\MolliePayments\Tests\Service\Transition;

use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\Transition\DeliveryTransitionService;
use Kiener\MolliePayments\Service\Transition\TransitionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class DeliveryTransitionServiceTest extends TestCase
{
    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var DeliveryTransitionService
     */
    private $deliveryTransitionService;

    /**
     * @var array[]
     */
    private $availableTransitions;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $this->deliveryTransitionService = new DeliveryTransitionService(
            new TransitionService($this->stateMachineRegistry),
            $this->createMock(LoggerService::class)
        );

        $this->context = $this->createMock(Context::class);

        // All possible transitions, current delivery state => possible actions
        $this->availableTransitions = [
            OrderDeliveryStates::STATE_OPEN => [
                StateMachineTransitionActions::ACTION_CANCEL,
                StateMachineTransitionActions::ACTION_SHIP,
                StateMachineTransitionActions::ACTION_SHIP_PARTIALLY
            ],
            OrderDeliveryStates::STATE_PARTIALLY_SHIPPED => [
                StateMachineTransitionActions::ACTION_CANCEL,
                StateMachineTransitionActions::ACTION_REOPEN,
                StateMachineTransitionActions::ACTION_RETOUR,
                StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY,
                StateMachineTransitionActions::ACTION_SHIP,
            ],
            OrderDeliveryStates::STATE_SHIPPED => [
                StateMachineTransitionActions::ACTION_CANCEL,
                StateMachineTransitionActions::ACTION_REOPEN,
                StateMachineTransitionActions::ACTION_RETOUR,
                StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY,
            ],
            OrderDeliveryStates::STATE_PARTIALLY_RETURNED => [
                StateMachineTransitionActions::ACTION_REOPEN,
                StateMachineTransitionActions::ACTION_RETOUR,
            ],
            OrderDeliveryStates::STATE_RETURNED => [
                StateMachineTransitionActions::ACTION_REOPEN,
            ],
            OrderDeliveryStates::STATE_CANCELLED => [
                StateMachineTransitionActions::ACTION_REOPEN,
            ]
        ];
    }

    /**
     * Tests if the reopen transition is performed directly
     * if the delivery is in a state at which reopen is allowed
     * (all, except open itself.)
     */
    public function testOpenDeliveryOnAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_CANCELLED);

        $expectedTransition = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_REOPEN);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->once())->method('transition')
            ->with($expectedTransition, $this->context);

        $this->deliveryTransitionService->reOpenDelivery($delivery, $this->context);
    }

    /**
     * Tests if no transition is performed when performing a reopen action on the delivery
     * if the delivery is already in an open state
     */
    public function testOpenDeliveryOnSameState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_OPEN);

        $this->stateMachineRegistry->expects($this->never())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->never())->method('transition');

        $this->deliveryTransitionService->reOpenDelivery($delivery, $this->context);
    }

    /**
     * Tests if the ship_partially transition is performed directly
     * if the delivery is in a state at which ship_partially is allowed
     * (all, except ship_partially itself.)
     */
    public function testPartialShipDeliveryOnAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_OPEN);

        $expectedTransition = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_SHIP_PARTIALLY);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->once())->method('transition')
            ->with($expectedTransition, $this->context);

        $this->deliveryTransitionService->partialShipDelivery($delivery, $this->context);
    }

    /**
     * Tests if no transition is performed when performing a ship_partially action on the delivery
     * if the delivery is already in a shipped_partially state
     */
    public function testPartialShipDeliveryOnSameState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_PARTIALLY_SHIPPED);

        $this->stateMachineRegistry->expects($this->never())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->never())->method('transition');

        $this->deliveryTransitionService->partialShipDelivery($delivery, $this->context);
    }

    /**
     * Tests if the ship transition is performed directly
     * if the delivery is in a state at which ship is allowed
     * (all, except ship_partially and ship itself.)
     */
    public function testShipDeliveryOnAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_OPEN);

        $expectedTransition = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_SHIP);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->once())->method('transition')
            ->with($expectedTransition, $this->context);

        $this->deliveryTransitionService->shipDelivery($delivery, $this->context);
    }

    /**
     * Tests if no transition is performed when performing a ship action on the delivery
     * if the delivery is already in a shipped state
     */
    public function testShipDeliveryOnSameState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_SHIPPED);

        $this->stateMachineRegistry->expects($this->never())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->never())->method('transition');

        $this->deliveryTransitionService->shipDelivery($delivery, $this->context);
    }

    /**
     * Tests if a reopen transition is performed first when performing a ship action on the delivery
     * if the delivery is in a state where the ship action is not allowed, excluding shipped.
     * (returned, returned_partially, cancelled)
     */
    public function testShipDeliveryOnNotAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_CANCELLED);

        $expectedTransition1 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_REOPEN);
        $expectedTransition2 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_SHIP);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->exactly(2))->method('transition')
            ->withConsecutive(
                [$expectedTransition1, $this->context],
                [$expectedTransition2, $this->context]
            );

        $this->deliveryTransitionService->shipDelivery($delivery, $this->context);
    }

    /**
     * Tests if the retour_partially transition is performed directly
     * if the delivery is in a state at which retour_partially is allowed
     * (ship_partially and ship)
     */
    public function testPartialReturnDeliveryOnAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_SHIPPED);

        $expectedTransition = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->once())->method('transition')
            ->with($expectedTransition, $this->context);

        $this->deliveryTransitionService->partialReturnDelivery($delivery, $this->context);
    }

    /**
     * Tests if no transition is performed when performing a ship action on the delivery
     * if the delivery is already in a returned_partially state
     */
    public function testPartialReturnDeliveryOnSameState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_PARTIALLY_RETURNED);

        $this->stateMachineRegistry->expects($this->never())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->never())->method('transition');

        $this->deliveryTransitionService->partialReturnDelivery($delivery, $this->context);
    }

    /**
     * Tests if reopen and ship transitions are performed first when performing a retour_partially action on the delivery
     * if the delivery is in a state where the retour_partially action is not allowed, excluding returned_partially.
     * (returned, open, cancelled)
     */
    public function testPartialReturnDeliveryOnNotAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_CANCELLED);

        $expectedTransition1 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_REOPEN);
        $expectedTransition2 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_SHIP);
        $expectedTransition3 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->exactly(3))->method('transition')
            ->withConsecutive(
                [$expectedTransition1, $this->context],
                [$expectedTransition2, $this->context],
                [$expectedTransition3, $this->context]
            );

        $this->deliveryTransitionService->partialReturnDelivery($delivery, $this->context);
    }

    /**
     * Tests if the retour transition is performed directly
     * if the delivery is in a state at which retour is allowed
     * (return_partially, ship_partially and ship)
     */
    public function testReturnDeliveryOnAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_SHIPPED);

        $expectedTransition = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_RETOUR);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->once())->method('transition')
            ->with($expectedTransition, $this->context);

        $this->deliveryTransitionService->returnDelivery($delivery, $this->context);
    }

    /**
     * Tests if no transition is performed when performing a retour action on the delivery
     * if the delivery is already in a returned state
     */
    public function testReturnDeliveryOnSameState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_RETURNED);

        $this->stateMachineRegistry->expects($this->never())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->never())->method('transition');

        $this->deliveryTransitionService->returnDelivery($delivery, $this->context);
    }

    /**
     * Tests if reopen and ship transitions are performed first when performing a retour action on the delivery
     * if the delivery is in a state where the retour action is not allowed, excluding returned.
     * (open, cancelled)
     */
    public function testReturnDeliveryOnNotAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_CANCELLED);

        $expectedTransition1 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_REOPEN);
        $expectedTransition2 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_SHIP);
        $expectedTransition3 = $this->createTransitionForAction(StateMachineTransitionActions::ACTION_RETOUR);

        $this->stateMachineRegistry->expects($this->once())->method('getAvailableTransitions');
        $this->stateMachineRegistry->expects($this->exactly(3))->method('transition')
            ->withConsecutive(
                [$expectedTransition1, $this->context],
                [$expectedTransition2, $this->context],
                [$expectedTransition3, $this->context]
            );

        $this->deliveryTransitionService->returnDelivery($delivery, $this->context);
    }


    private function createDeliveryWithState(string $state): OrderDeliveryEntity
    {
        $this->setUpTransitionsForDeliveryState($state);

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setTechnicalName($state);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('deliveryId');
        $delivery->setStateMachineState($stateMachineState);

        return $delivery;
    }

    private function setUpTransitionsForDeliveryState(string $state): void
    {
        $transitions = [];
        foreach ($this->availableTransitions[$state] as $action) {
            $transition = new StateMachineTransitionEntity();
            $transition->setId('transitionId1');
            $transition->setActionName($action);
            $transitions[] = $transition;
        }

        $this->stateMachineRegistry
            ->method('getAvailableTransitions')
            ->with(OrderDeliveryDefinition::ENTITY_NAME, 'deliveryId', 'stateId', $this->context)
            ->willReturn($transitions);
    }

    private function createTransitionForAction($action)
    {
        return new Transition(
            OrderDeliveryDefinition::ENTITY_NAME,
            'deliveryId',
            $action,
            'stateId'
        );
    }
}
