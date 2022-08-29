<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Transition;

use Kiener\MolliePayments\Compatibility\CompatibilityFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class TransactionTransitionService implements TransactionTransitionServiceInterface
{
    /**
     * @var TransitionServiceInterface
     */
    private $transitionService;

    /**
     * @var CompatibilityFactory
     */
    private $compatibilityFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TransitionServiceInterface $transitionService
     * @param CompatibilityFactory $compatibilityFactory
     * @param LoggerInterface $loggerService
     */
    public function __construct(
        TransitionServiceInterface $transitionService,
        CompatibilityFactory       $compatibilityFactory,
        LoggerInterface            $loggerService
    ) {
        $this->transitionService = $transitionService;
        $this->compatibilityFactory = $compatibilityFactory;
        $this->logger = $loggerService;
    }

    public function processTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        // Shopware added in_progress status with version 6.2, so this ensures backward compatibility
        if (!defined('Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_IN_PROGRESS')) {
            // set open status in < sw6.2
            $this->reOpenTransaction($transaction, $context);

            return;
        }

        $technicalName = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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
        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';
        $currentStatusName = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getName() : '';

        if ($this->isFinalOrTargetStatus($currentStatus, [OrderTransactionStates::STATE_OPEN])) {
            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REOPEN, $availableTransitions)) {
            $this->logger->error(
                sprintf(
                    'It is not allowed to change status to open from %s. Aborting reopen transition',
                    $currentStatusName
                )
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

        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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
        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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

        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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

        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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
        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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
        $currentStatus = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

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

    public function chargebackTransaction(OrderTransactionEntity $transaction, Context $context): void
    {
        $compatibilityGateway = $this->compatibilityFactory->createGateway();

        $chargebackState = $compatibilityGateway->getChargebackOrderTransactionState();

        $currentState = ($transaction->getStateMachineState() instanceof StateMachineStateEntity) ? $transaction->getStateMachineState()->getTechnicalName() : '';

        if ($this->isFinalOrTargetStatus($currentState, [$chargebackState])) {
            return;
        }

        if ($chargebackState !== 'chargeback') {
            $this->processTransaction($transaction, $context);
            return;
        }

        $entityId = $transaction->getId();
        $availableTransitions = $this->getAvailableTransitions($entityId, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_CHARGEBACK, $availableTransitions)) {
            $this->payTransaction($transaction, $context);
        }

        $this->performTransition($entityId, StateMachineTransitionActions::ACTION_CHARGEBACK, $context);
    }

    /**
     * @param string $currentStatus
     * @param array<mixed> $targetStatus
     * @return bool
     */
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

    /**
     * @param string $transition
     * @param array<mixed> $availableTransitions
     * @return bool
     */
    private function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return $this->transitionService->transitionIsAllowed($transition, $availableTransitions);
    }

    /**
     * @param string $entityId
     * @param Context $context
     * @return array<mixed>
     */
    private function getAvailableTransitions(string $entityId, Context $context): array
    {
        return $this->transitionService->getAvailableTransitions(OrderTransactionDefinition::ENTITY_NAME, $entityId, $context);
    }

    private function performTransition(string $entityId, string $transitionName, Context $context): void
    {
        $this->transitionService->performTransition(OrderTransactionDefinition::ENTITY_NAME, $entityId, $transitionName, $context);
    }
}
