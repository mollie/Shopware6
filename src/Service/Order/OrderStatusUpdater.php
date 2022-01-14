<?php

namespace Kiener\MolliePayments\Service\Order;

use Kiener\MolliePayments\Repository\Order\OrderRepository;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderStatusUpdater
{

    /**
     * @var OrderTransactionStateHandler
     */
    private $transitionHandler;

    /**
     * @var OrderStateService
     */
    private $orderHandler;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var OrderRepository
     */
    private $repoOrders;


    /**
     *
     */
    private const STATE_REFUNDED = 'refunded';

    /**
     *
     */
    private const STATE_REFUNDED_PARTIALLY = 'refunded_partially';

    /** @var TransactionTransitionServiceInterface */
    private $transactionTransitionService;


    /**
     * @param OrderTransactionStateHandler $transitionHandler
     * @param OrderStateService $orderHandler
     * @param StateMachineRegistry $stateMachineRegistry
     * @param TransactionTransitionServiceInterface $transactionTransitionService
     * @param OrderRepository $repoOrders
     */
    public function __construct(OrderTransactionStateHandler $transitionHandler, OrderStateService $orderHandler, StateMachineRegistry $stateMachineRegistry, TransactionTransitionServiceInterface $transactionTransitionService, OrderRepository $repoOrders)
    {
        $this->transitionHandler = $transitionHandler;
        $this->orderHandler = $orderHandler;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->repoOrders = $repoOrders;
    }


    /**
     * @param OrderTransactionEntity $transaction
     * @param string $status
     * @param Context $context
     * @throws \Exception
     */
    public function updatePaymentStatus(OrderTransactionEntity $transaction, string $status, Context $context): void
    {
        $transactionState = $transaction->getStateMachineState();

        if (!$transactionState instanceof StateMachineStateEntity) {
            return;
        }

        # if we already have the target status then
        # skip this progress and don't do anything
        if ($transactionState->getTechnicalName() === $status) {
            return;
        }

        switch ($status) {
            case MolliePaymentStatus::MOLLIE_PAYMENT_OPEN:
                $this->transactionTransitionService->reOpenTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $this->transactionTransitionService->authorizeTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK:
                $this->transactionTransitionService->chargebackTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
            case MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $this->transactionTransitionService->payTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $this->transactionTransitionService->failTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
                $this->transactionTransitionService->cancelTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
                $this->transactionTransitionService->refundTransaction($transaction, $context);

                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $this->transitionHandler->refundPartially($transaction->getId(), $context);

                break;
            default:
                throw new \Exception('Updating Payment Status of Order not possible for status: ' . $status);
        }

        # last but not least,
        # also update the lastUpdated of the order itself
        # this is required for ERP systems and more (so they know something has changed).
        $this->repoOrders->updateOrderLastUpdated(
            $transaction->getOrder()->getId(),
            $context
        );
    }

    /**
     * @param OrderEntity $order
     * @param string $status
     * @param MollieSettingStruct $settings
     * @param Context $context
     * @throws \Exception
     */
    public function updateOrderStatus(OrderEntity $order, string $status, MollieSettingStruct $settings, Context $context): void
    {
        switch ($status) {

            case MolliePaymentStatus::MOLLIE_PAYMENT_OPEN:
            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:
            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAAuthorizedTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
            case MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAPaidTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAFailedTransaction(), $context);
                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithACancelledTransaction(), $context);
                break;
            case MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAChargebackTransaction(), $context);
                break;

            default:
                throw new \Exception('Updating Order Status of Order not possible for status: ' . $status);
        }
    }

}
