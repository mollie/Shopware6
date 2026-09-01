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
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
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

        // Only execute the actions when they really lead step by step to the target state. Otherwise the
        // target cannot be reached and we must not move the order through wrong states on the way.
        if (count($transitionActions) === 0 || ! $this->pathLeadsToTarget($transitionList, $currentState, $transitionActions, $targetState)) {
            $possibleTransitions = [];
            foreach ($this->getAllowedTransitions($transitionList, $currentState) as $allowedTransition) {
                $possibleTransitions[] = $allowedTransition->getActionName();
            }

            throw new IllegalTransitionException($currentState, $targetState, $possibleTransitions);
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

    /**
     * @param string[] $actions
     */
    private function pathLeadsToTarget(StateMachineTransitionCollection $transitions, string $currentState, array $actions, string $targetState): bool
    {
        $state = $currentState;
        foreach ($actions as $action) {
            $nextState = null;
            foreach ($this->getAllowedTransitions($transitions, $state) as $transition) {
                $toState = $transition->getToStateMachineState();
                if ($toState !== null && $transition->getActionName() === $action) {
                    $nextState = $toState->getTechnicalName();
                    break;
                }
            }
            if ($nextState === null) {
                return false;
            }
            $state = $nextState;
        }

        return $state === $targetState;
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
     * @param string[] $visitedStates
     *
     * @return string[]
     */
    private function loadTransitionActions(StateMachineTransitionCollection $transitions, string $currentState, string $targetState, array $visitedStates = []): array
    {
        // Remember which target states have already been traced on this branch. Without this a freely
        // configured state machine with a cycle (e.g. reopen leading back to a state we came from) would
        // trace the same way again and again and recurse endlessly.
        if (in_array($targetState, $visitedStates, true)) {
            return [];
        }
        $visitedStates[] = $targetState;

        $result = [];
        $allowedTransitions = $this->getAllowedTransitions($transitions, $currentState);
        // If there is only one allowed actions, then we execute that action
        if ($allowedTransitions->count() === 1) {
            $firstTransition = $allowedTransitions->first();
            if ($firstTransition instanceof StateMachineTransitionEntity) {
                $result[] = $firstTransition->getActionName();
                $toState = $firstTransition->getToStateMachineState();
                if ($toState instanceof StateMachineStateEntity) {
                    $currentState = $toState->getTechnicalName();
                    if ($currentState === $targetState) {
                        return $result;
                    }
                }
            }
        }
        // If there are more than one way, then we look at the target state and try to find a way how to reach the target state
        // imagine like a maze, you know where the target is, you trace your way back from target to start
        $reverseResults = [];
        /** @var StateMachineTransitionEntity $transition */
        foreach ($transitions as $transition) {
            $toState = $transition->getToStateMachineState();
            if ($toState === null) {
                continue;
            }
            if ($toState->getTechnicalName() !== $targetState) {
                continue;
            }

            $fromState = $transition->getFromStateMachineState();
            if ($fromState === null) {
                continue;
            }
            $reverseResults[] = $transition->getActionName();

            if ($fromState->getTechnicalName() === $currentState) {
                return array_merge($result, array_reverse($reverseResults));
            }
            $subResult = $this->loadTransitionActions($transitions, $currentState, $fromState->getTechnicalName(), $visitedStates);

            $reverseResults = array_merge($reverseResults, $subResult);
        }

        return array_merge($result, array_reverse($reverseResults));
    }
}
