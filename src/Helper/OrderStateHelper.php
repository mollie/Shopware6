<?php

namespace Kiener\MolliePayments\Helper;

use Exception;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderStateHelper
{
    /** @var LoggerService */
    protected $logger;

    /** @var StateMachineRegistry */
    protected $stateMachineRegistry;

    public function __construct(
        LoggerService $logger,
        StateMachineRegistry $stateMachineRegistry
    )
    {
        $this->logger = $logger;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * Handle order state automation.
     *
     * @param OrderEntity $order
     * @param string $orderState
     * @param Context $context
     *
     * @return bool
     */
    public function setOrderState(OrderEntity $order, string $orderState, Context $context): bool
    {
        // if order state is skip we don't set a new order state
        if ($orderState === MollieSettingStruct::ORDER_STATE_SKIP) {
            return false;
        }

        $currentStatus = $order->getStateMachineState()->getTechnicalName();

        // if current state is same as status that shoould be set, we don't need to do a transition
        if ($currentStatus === $orderState) {
            return false;
        }

        try {
            $this->stateMachineRegistry->transition(
                new Transition(
                    OrderDefinition::ENTITY_NAME,
                    $order->getId(),
                    StateMachineTransitionActions::ACTION_REOPEN,
                    'stateId'
                ),
                $context
            );
        } catch (Exception $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context,
                $e,
                [
                    'function' => 'payment-automate-order-state',
                ]
            );
        }

        $completedOrCancelled = false;
        // Collect an array of possible order states
        $orderStates = [
            OrderStates::STATE_OPEN,
            OrderStates::STATE_IN_PROGRESS,
            OrderStates::STATE_COMPLETED,
            OrderStates::STATE_CANCELLED,
        ];

        // Check if the order state is valid
        if (!in_array($orderState, $orderStates, true)) {
            return false;
        }

        // Get the transition name
        if ($orderState === OrderStates::STATE_OPEN) {
            $transitionName = StateMachineTransitionActions::ACTION_REOPEN;
        }

        if ($orderState === OrderStates::STATE_IN_PROGRESS) {
            $transitionName = StateMachineTransitionActions::ACTION_PROCESS;
        }

        if ($orderState === OrderStates::STATE_COMPLETED ||
            $orderState === OrderStates::STATE_CANCELLED
        ) {
            $completedOrCancelled = true;
        }

        if ($orderState === OrderStates::STATE_COMPLETED) {
            $transitionName = StateMachineTransitionActions::ACTION_COMPLETE;
        }

        if ($orderState === OrderStates::STATE_CANCELLED) {
            $transitionName = StateMachineTransitionActions::ACTION_CANCEL;
        }

        if ($completedOrCancelled) {
            try {
                $this->stateMachineRegistry->transition(
                    new Transition(
                        OrderDefinition::ENTITY_NAME,
                        $order->getId(),
                        StateMachineTransitionActions::ACTION_PROCESS,
                        'stateId'
                    ),
                    $context
                );
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-automate-order-state',
                    ]
                );
            }
        }
        // Transition the order
        if (isset($transitionName)) {
            try {
                $this->stateMachineRegistry->transition(
                    new Transition(
                        OrderDefinition::ENTITY_NAME,
                        $order->getId(),
                        $transitionName,
                        'stateId'
                    ),
                    $context
                );
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-automate-order-state',
                    ]
                );
            }
        }

        return true;
    }
}
