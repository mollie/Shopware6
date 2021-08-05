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
        // Shopware added in_progress status with version 6.2, so this ensures backward compatibility
        if (!defined('Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_IN_PROGRESS')) {
            // set open status in < sw6.2
            $this->reOpenTransaction($transaction, $context);

            return;
        }

        $technicalName = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($technicalName, [OrderTransactionStates::STATE_IN_PROGRESS])) {

            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_DO_PAY, $availableTransitions)) {
            $this->reOpenTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_DO_PAY, $context);
    }

    public function reOpenTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [OrderTransactionStates::STATE_OPEN])) {

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

    public function payTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        // backwards compatibility, the former status StateMachineTransitionActions::ACTION_PAY='pay' does not exist any more
        // the constant ACTION_PAID has been added with sw 6.2 and should be used instead of legacy ACTION_PAY
        $payActionName = 'pay';

        if (defined('Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_PAID')) {
            $payActionName = StateMachineTransitionActions::ACTION_PAID;
        }

        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [$payActionName])) {
            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed($payActionName, $availableTransitions)) {
            $this->reOpenTransaction($transaction, $context);
        }

        $this->performTransition($entityId, $payActionName, $context);
    }

    public function cancelTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [OrderTransactionStates::STATE_CANCELLED])) {

            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_CANCEL, $availableTransitions)) {
            $this->reOpenTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_CANCEL, $context);
    }

    public function failTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        // Shopware added failed status with version 6.2, so this ensures backward compatibility
        if (!defined('Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_FAILED')) {
            $this->cancelTransaction($transaction, $context);

            return;
        }

        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [OrderTransactionStates::STATE_CANCELLED, OrderTransactionStates::STATE_FAILED])) {

            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_FAIL, $availableTransitions)) {
            $this->reOpenTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_FAIL, $context);
    }

    public function authorizeTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        // Shopware added authorized status with version 6.4.1, so this ensures backward compatibility
        if (!defined('Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_AUTHORIZED')) {
            $this->payTransaction($transaction, $context);

            return;
        }

        $authorizedState = OrderTransactionStates::STATE_AUTHORIZED;

        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [$authorizedState, OrderTransactionStates::STATE_PAID])) {

            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_AUTHORIZE, $availableTransitions)) {
            $this->reOpenTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_AUTHORIZE, $context);
    }

    public function refundTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [OrderTransactionStates::STATE_REFUNDED])) {

            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REFUND, $availableTransitions)) {
            $this->payTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_REFUND, $context);
    }

    public function partialRefundTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        $currentStatus = $transaction->getStateMachineState()->getTechnicalName();

        if ($this->isFinalOrTargetStatus($currentStatus, [OrderTransactionStates::STATE_PARTIALLY_REFUNDED])) {

            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REFUND_PARTIALLY, $availableTransitions)) {
            $this->payTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_REFUND_PARTIALLY, $context);
    }

    private function isFinalOrTargetStatus(string $currentStatus, array $targetStatus): bool
    {
        if ($this->isFinalStatus($currentStatus)) {
            return true;
        }

        return in_array($currentStatus, $targetStatus);
    }

    private function isFinalStatus(string $orderTransactionStatus): bool
    {
        return $orderTransactionStatus === OrderTransactionStates::STATE_REFUNDED;
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
