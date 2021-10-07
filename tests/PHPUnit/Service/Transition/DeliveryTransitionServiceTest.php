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

        // All possible transitions
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

    public function testOpenDeliveryOnAllowedState()
    {
        $delivery = $this->createDeliveryWithState(OrderDeliveryStates::STATE_CANCELLED);

        $this->deliveryTransitionService->reOpenDelivery($delivery, $this->context);
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
        foreach($this->availableTransitions[$state] as $action) {
            $transition = new StateMachineTransitionEntity();
            $transition->setId('transitionId1');
            $transition->setActionName($action);
            $transitions[] = $transition;
        }

        $this->stateMachineRegistry
            ->expects($this->once())
            ->method('getAvailableTransitions')
            ->with(OrderDeliveryDefinition::ENTITY_NAME, 'deliveryId', 'stateId', $this->context)
            ->willReturn($transitions);
    }

    private function createTransitionForAction($action) {
        return new Transition(
            OrderDeliveryDefinition::ENTITY_NAME,
            'deliveryId',
            StateMachineTransitionActions::ACTION_REOPEN,
            'stateId'
        );
    }
}
