<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StateHandler;

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
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function performTransition(OrderEntity $shopwareOrder, StateMachineStateEntity $currentOrderState, string $targetState, Context $context): string
    {
        $orderNumber = $shopwareOrder->getOrderNumber();
        $salesChannelId = $shopwareOrder->getSalesChannelId();
        $orderId = $shopwareOrder->getId();
        $currentState = $currentOrderState->getTechnicalName();
        $logData = [
            'currentState' => $currentState,
            'targetState' => $targetState,
            'orderId' => $orderId,
            'salesChannelId' => $salesChannelId,
            'orderNumber' => $orderNumber,
        ];

        if ($targetState === $currentState) {
            $this->logger->debug('Order is already in current status', $logData);

            return $targetState;
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
        $transitionActions = $this->loadTransitionActions($transitionList,$currentState,$targetState);
        if (count($transitionActions) === 0) {
            $this->logger->error('Failed to find a way to move the order transaction, please check your state machine state',$logData);

            return $movedToTransactionId;
        }

        foreach ($transitionActions as $action) {
            $this->logger->debug(sprintf("Use action '%s' to move order state",$action), $logData);
            $result = $this->stateMachineRegistry->transition(new Transition(
                OrderDefinition::ENTITY_NAME,
                $orderId,
                $action,
                'stateId'
            ),$context);
            /** @var StateMachineStateEntity $movedToTransaction */
            $movedToTransaction = $result->get('toPlace');
            $movedToTransactionId = $movedToTransaction->getId();
        }
        $this->logger->info('Transitioned order status', $logData);

        return $movedToTransactionId;
    }

    private function getAllowedTransitions(StateMachineTransitionCollection $transitions,string $currentState): StateMachineTransitionCollection
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
    private function loadTransitionActions(StateMachineTransitionCollection $transitions,string $currentState, string $targetState): array
    {
        $result = [];
        $allowedTransitions = $this->getAllowedTransitions($transitions,$currentState);
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
                return array_merge($result,array_reverse($reverseResults));
            }
            $subResult = $this->loadTransitionActions($transitions,$currentState,$fromState->getTechnicalName());

            $reverseResults = array_merge($reverseResults,$subResult);
        }

        return array_merge($result,array_reverse($reverseResults));
    }
}
