<?php

namespace Kiener\MolliePayments\Service\Order;

use Kiener\MolliePayments\Repository\Order\OrderRepository;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
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
     * @param string $targetShopwareStatusKey
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function updatePaymentStatus(OrderTransactionEntity $transaction, string $targetShopwareStatusKey, Context $context): void
    {
        $currentShopwareState = $transaction->getStateMachineState();

        if (!$currentShopwareState instanceof StateMachineStateEntity) {
            return;
        }

        $currentShopwareStatusKey = $currentShopwareState->getTechnicalName();


        # if we already have the target status then
        # skip this progress and don't do anything
        if ($currentShopwareStatusKey === $targetShopwareStatusKey) {
            return;
        }

        switch ($targetShopwareStatusKey) {
            case MolliePaymentStatus::MOLLIE_PAYMENT_OPEN:
                {
                    # if we are already in_progress...then don't switch to OPEN again
                    # otherwise SEPA bank transfer would switch back to OPEN
                    if ($currentShopwareStatusKey !== OrderTransactionStates::STATE_IN_PROGRESS) {
                        $this->transactionTransitionService->reOpenTransaction($transaction, $context);
                    }
                }
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
                $this->transactionTransitionService->failTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $this->transactionTransitionService->cancelTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
                $this->transactionTransitionService->refundTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $this->transitionHandler->refundPartially($transaction->getId(), $context);
                break;

            default:
                throw new \Exception('Updating Payment Status of Order not possible for status: ' . $targetShopwareStatusKey);
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
        # let's check if we have configured a final order state.
        # if so, we need to verify, if a transition is even allowed
        if (!empty($settings->getOrderStateFinalState())) {

            $currentId = $order->getStateMachineState()->getId();

            # test if our current order does already have
            # our configured final order state
            if ($currentId === $settings->getOrderStateFinalState()) {

                $allowedList = [
                    MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED,
                    MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED,
                    MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK,
                ];

                # once our final state is reached, we only allow transitions
                # to chargebacks and refunds.
                # all other transitions will not happen.
                if (!in_array($status, $allowedList)) {
                    return;
                }
            }
        }


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

            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAFailedTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
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
