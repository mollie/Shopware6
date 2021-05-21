<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Transition;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class OrderTransitionService implements OrderTransitionServiceInterface
{
    /**
     * @var TransitionServiceInterface
     */
    private $transitionService;

    public function __construct(TransitionServiceInterface $transitionService)
    {
        $this->transitionService = $transitionService;
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
     * Gets the currently available transitions for the order entity
     *
     * @param OrderEntity $order
     * @param Context $context
     * @return array<string>
     */
    private function getAvailableTransitions(OrderEntity $order, Context $context): array
    {
        return $this->transitionService->getAvailableTransitions(OrderDefinition::ENTITY_NAME, $order->getId(), $context);
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
        return $this->transitionService->transitionIsAllowed($transition, $availableTransitions);
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
        $this->transitionService->performTransition(OrderDefinition::ENTITY_NAME, $order->getId(), $transitionName, $context);
    }
}
