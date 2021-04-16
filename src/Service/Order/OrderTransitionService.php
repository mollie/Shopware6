<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;


use Kiener\MolliePayments\Service\LoggerService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderTransitionService implements OrderTransitionServiceInterface
{
    /** @var LoggerService */
    private $logger;

    /** @var StateMachineRegistry */
    private $stateMachineRegistry;

    public function __construct(LoggerService $logger, StateMachineRegistry $stateMachineRegistry)
    {
        $this->logger = $logger;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * Gets the currently available transitions for the order entity
     *
     * @param OrderEntity $order
     * @param Context $context
     * @return array<string>
     */
    public function getAvailableTransitions(OrderEntity $order, Context $context): array
    {
        /** @var array<StateMachineTransitionEntity> $availableTransitions */
        $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions(
            OrderDefinition::ENTITY_NAME,
            $order->getId(),
            'stateId',
            $context
        );

        return array_map(function(StateMachineTransitionEntity $transition) {
            return $transition->getActionName();
        }, $availableTransitions);
    }

    /**
     * Performs the required transitions to get the order to open state from any
     * Shopware default state
     *
     * @param OrderEntity $order
     * @param Context $context
     */
    public function openOrder(OrderEntity $order, Context $context): void
    {
        if ($order->getStateMachineState()->getName() === OrderStates::STATE_OPEN) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REOPEN, $availableTransitions)) {
            $this->performTransition($order, StateMachineTransitionActions::ACTION_COMPLETE, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_REOPEN, $context);
    }

    /**
     * Performs the required transitions to get the order to in_process state from any
     * Shopware default state
     *
     * @param OrderEntity $order
     * @param Context $context
     */
    public function processOrder(OrderEntity $order, Context $context): void
    {
        if ($order->getStateMachineState()->getName() === OrderStates::STATE_IN_PROGRESS) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_PROCESS, $availableTransitions)) {
            $this->performTransition($order, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_PROCESS, $context);
    }

    /**
     * Performs the required transitions to get the order to complete state from any
     * Shopware default state
     *
     * @param OrderEntity $order
     * @param Context $context
     */
    public function completeOrder(OrderEntity $order, Context $context): void
    {
        if ($order->getStateMachineState()->getName() === OrderStates::STATE_COMPLETED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_COMPLETE, $availableTransitions)) {
            $this->processOrder($order, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_COMPLETE, $context);
    }

    /**
     * Performs the required transitions to get the order to cancelled state from any
     * Shopware default state
     *
     * @param OrderEntity $order
     * @param Context $context
     */
    public function cancelOrder(OrderEntity $order, Context $context): void
    {
        if ($order->getStateMachineState()->getName() === OrderStates::STATE_CANCELLED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_CANCEL, $availableTransitions)) {
            $this->performTransition($order, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_CANCEL, $context);
    }

    /**
     * Checks if the requested transition is allowed for the current order state
     *
     * @param string $transition
     * @param array $availableTransitions
     * @return bool
     */
    private function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return in_array($transition, $availableTransitions);
    }

    /**
     * Performs the order transition
     *
     * @param OrderEntity $order
     * @param string $transitionName
     * @param Context $context
     */
    private function performTransition(OrderEntity $order, string $transitionName, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(
                OrderDefinition::ENTITY_NAME,
                $order->getId(),
                $transitionName,
                'stateId'
            ),
            $context
        );
    }
}
