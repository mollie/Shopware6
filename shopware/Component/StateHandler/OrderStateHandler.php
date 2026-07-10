<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StateHandler;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderStateHandler implements OrderStateHandlerInterface
{
    /**
     * @param EntityRepository<StateMachineTransitionCollection<StateMachineTransitionEntity>> $stateMachineRepository
     */
    public function __construct(
        #[Autowire(service: 'state_machine_transition.repository')]
        private readonly EntityRepository $stateMachineRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function performTransition(OrderEntity $shopwareOrder, string $shopwarePaymentStatus, string $currentState, string $salesChannelId, Context $context): string
    {
        $orderNumber = $shopwareOrder->getOrderNumber();
        $salesChannelId = $shopwareOrder->getSalesChannelId();
        $orderId = $shopwareOrder->getId();
        $currentOrderStateId = $shopwareOrder->getStateId();

        $orderStateSettings = $this->settingsService->getOrderStateSettings($salesChannelId);
        $finalOrderStateId = (string) $orderStateSettings->getFinalOrderState();

        $logData = [
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'orderId' => $orderId,
            'currentOrderStateId' => $currentOrderStateId,
            'currentState' => $currentState,
            'finalOrderStateId' => $finalOrderStateId,
        ];

        if ($finalOrderStateId === $currentOrderStateId) {
            $this->logger->debug('Order is in final state, changing skipped', $logData);

            return $currentOrderStateId;
        }

        $targetState = $orderStateSettings->getStatus($shopwarePaymentStatus);

        if ($targetState === null) {
            $this->logger->debug('Target order status is not configured for shopware payment status', $logData);

            return $currentOrderStateId;
        }

        $logData['targetState'] = $targetState;

        if ($targetState === $currentState) {
            $this->logger->debug('Order is already in current status', $logData);

            return $currentOrderStateId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', OrderStates::STATE_MACHINE));
        $criteria->addAssociation('stateMachine');
        $criteria->addAssociation('toStateMachineState');
        $criteria->addAssociation('fromStateMachineState');

        $searchResult = $this->stateMachineRepository->search($criteria, $context);
        /** @var StateMachineTransitionCollection $transitionList */
        $transitionList = $searchResult->getEntities();

        $movedToTransactionId = '';
        $transitionActions = $this->loadTransitionActions($transitionList, $currentState, $targetState);

        if (count($transitionActions) === 0) {
            $this->logger->error('Failed to find a way to move the order transaction, please check your state machine state', $logData);
            $message = sprintf('Could not find a way to move the order status from %s to status %s', $currentState, $targetState);
            throw new \RuntimeException($message);
        }

        foreach ($transitionActions as $action) {
            $this->logger->debug(sprintf("Use action '%s' to move order state", $action), $logData);
            $result = $this->stateMachineRegistry->transition(new Transition(
                OrderDefinition::ENTITY_NAME,
                $orderId,
                $action,
                'stateId'
            ), $context);
            /** @var StateMachineStateEntity $movedToTransaction */
            $movedToTransaction = $result->get('toPlace');
            $movedToTransactionId = $movedToTransaction->getId();
        }

        return $movedToTransactionId;
    }

    private function getAllowedTransitions(StateMachineTransitionCollection $transitions, string $currentState): StateMachineTransitionCollection
    {
        $result = new StateMachineTransitionCollection();
        foreach ($transitions as $transition) {
            $fromState = $transition->getFromStateMachineState();
            if ($fromState === null) {
                continue;
            }
            if ($fromState->getTechnicalName() === $currentState) {
                $result->add($transition);
            }
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function loadTransitionActions(StateMachineTransitionCollection $transitions, string $currentState, string $targetState): array
    {
        // Breadth-first search for the shortest sequence of actions leading from the current
        // state to the target state. The visited set guards against the cycles in the order
        // state machine (e.g. open -> cancelled -> open) that would otherwise recurse infinitely.
        $queue = [[$currentState, []]];
        $visited = [$currentState => true];

        while (count($queue) > 0) {
            /** @var array{0: string, 1: string[]} $current */
            $current = array_shift($queue);
            [$state, $actions] = $current;

            if ($state === $targetState) {
                return $actions;
            }

            foreach ($this->getAllowedTransitions($transitions, $state) as $transition) {
                $toState = $transition->getToStateMachineState();
                if ($toState === null) {
                    continue;
                }
                $nextState = $toState->getTechnicalName();
                if (isset($visited[$nextState])) {
                    continue;
                }
                $visited[$nextState] = true;
                $queue[] = [$nextState, array_merge($actions, [$transition->getActionName()])];
            }
        }

        return [];
    }
}
