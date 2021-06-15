<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Transition;

use Kiener\MolliePayments\Service\LoggerService;
use Monolog\Logger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class TransactionTransitionService implements TransactionTransitionServiceInterface
{
    /**
     * @var TransitionServiceInterface
     */
    private $transitionService;
    /**
     * @var LoggerService
     */
    private $loggerService;

    public function __construct(TransitionServiceInterface $transitionService, LoggerService $loggerService)
    {
        $this->transitionService = $transitionService;
        $this->loggerService = $loggerService;
    }

    public function processTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        if ($transaction->getStateMachineState()->getName() === OrderTransactionStates::STATE_IN_PROGRESS) {
            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_PROCESS, $availableTransitions)) {
            $this->performTransition($entityId, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_PROCESS, $context);
    }

    public function reOpenTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        if ($transaction->getStateMachineState()->getName() === OrderTransactionStates::STATE_OPEN) {
            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REOPEN, $availableTransitions)) {
            $this->loggerService->addEntry(
                sprintf(
                    'It is not allowed to change status to open from %s. Aborting reopen transition',
                    $transaction->getStateMachineState()->getName()
                ),
                $context,
                null,
                null,
                Logger::ERROR
            );

            return;
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_REOPEN, $context);
    }

    private function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return $this->transitionService->transitionIsAllowed($transition, $availableTransitions);
    }

    private function getAvailableTransitions(string $entityId, Context $context): array
    {
        return $this->transitionService->getAvailableTransitions(OrderTransactionDefinition::ENTITY_NAME, $entityId, $context);
    }

    private function performTransition(string $entityId, string $transitionName, Context $context): void
    {
        $this->transitionService->performTransition(OrderTransactionDefinition::ENTITY_NAME, $entityId, $transitionName, $context);
    }
}
