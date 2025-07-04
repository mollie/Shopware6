<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderStatusUpdater
{
    public const ORDER_STATE_FORCE_OPEN = 'order-state-force-open';
    /**
     * @var OrderStateService
     */
    private $orderHandler;

    /**
     * @var EntityRepository<EntityCollection<OrderEntity>>
     */
    private $repoOrders;

    /**
     * @var TransactionTransitionServiceInterface
     */
    private $transactionTransitionService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityRepository<EntityCollection<StateMachineStateEntity>>
     */
    private $stateMachineStateRepository;

    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $repoOrders
     * @param EntityRepository<EntityCollection<StateMachineStateEntity>> $stateMachineStateRepository
     */
    public function __construct(OrderStateService $orderHandler, $repoOrders, TransactionTransitionServiceInterface $transactionTransitionService, $stateMachineStateRepository, LoggerInterface $logger)
    {
        $this->orderHandler = $orderHandler;
        $this->repoOrders = $repoOrders;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->logger = $logger;

        $this->stateMachineStateRepository = $stateMachineStateRepository;
    }

    /**
     * @throws \Exception
     */
    public function updatePaymentStatus(OrderTransactionEntity $transaction, string $targetShopwareStatusKey, Context $context): void
    {
        // always fetch new, (race condition between storefront and webhooks at the same time)
        $criteria = new Criteria([$transaction->getStateId()]);
        $searchResult = $this->stateMachineStateRepository->search($criteria, $context);

        /** @var ?StateMachineStateEntity OrderStatusUpdater.php */
        $currentShopwareState = $searchResult->first();

        if (! $currentShopwareState instanceof StateMachineStateEntity) {
            return;
        }

        $order = $transaction->getOrder();

        if (! $order instanceof OrderEntity) {
            return;
        }

        $currentShopwareStatusKey = $currentShopwareState->getTechnicalName();

        // if we already have the target status then
        // skip this progress and don't do anything
        if ($currentShopwareStatusKey === $targetShopwareStatusKey) {
            return;
        }

        $addLog = false;

        switch ($targetShopwareStatusKey) {
            case MolliePaymentStatus::MOLLIE_PAYMENT_OPEN:
                $states = [OrderTransactionStates::STATE_IN_PROGRESS];
                if (defined('\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_UNCONFIRMED')) {
                    $states[] = OrderTransactionStates::STATE_UNCONFIRMED;
                }
                // if we are already in_progress...then don't switch to OPEN again
                // otherwise SEPA bank transfer would switch back to OPEN
                if (! in_array($currentShopwareStatusKey, $states) || $context->hasState(self::ORDER_STATE_FORCE_OPEN)) {
                    $addLog = true;
                    $this->transactionTransitionService->reOpenTransaction($transaction, $context);
                }

                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $addLog = true;
                $this->transactionTransitionService->authorizeTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK:
                $addLog = true;
                $this->transactionTransitionService->chargebackTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
            case MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $addLog = true;
                $this->transactionTransitionService->payTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
                $addLog = true;
                $this->transactionTransitionService->failTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $addLog = true;
                $this->transactionTransitionService->cancelTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
                $addLog = true;
                $this->transactionTransitionService->refundTransaction($transaction, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $addLog = true;
                $this->transactionTransitionService->partialRefundTransaction($transaction, $context);
                break;

            default:
                throw new \Exception('Updating Payment Status of Order not possible for status: ' . $targetShopwareStatusKey);
        }

        if ($addLog) {
            $this->logger->debug(
                'Payment status transition of order ' . $order->getOrderNumber() . ' from "' . $currentShopwareStatusKey . '" to "' . $targetShopwareStatusKey . '"',
                [
                    'order' => $order->getOrderNumber(),
                    'statusFrom' => $currentShopwareStatusKey,
                    'statusTo' => $targetShopwareStatusKey,
                ]
            );
        }

        // last but not least,
        // also update the lastUpdated of the order itself
        // this is required for ERP systems and more (so they know something has changed).

        $this->repoOrders->update([
            [
                'id' => $order->getId(),
                'updatedAt' => new \DateTime(),
            ],
        ], $context);
    }

    /**
     * @throws \Exception
     */
    public function updateOrderStatus(OrderEntity $order, string $statusTo, MollieSettingStruct $settings, Context $context): void
    {
        $stateMachine = $order->getStateMachineState();

        // let's check if we have configured a final order state.
        // if so, we need to verify, if a transition is even allowed
        if (! empty($settings->getOrderStateFinalState())) {
            $currentId = ($stateMachine instanceof StateMachineStateEntity) ? $stateMachine->getId() : $order->getStateId();

            // test if our current order does already have
            // our configured final order state
            if ($currentId === $settings->getOrderStateFinalState()) {
                $allowedList = [
                    MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED,
                    MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED,
                    MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK,
                ];

                // once our final state is reached, we only allow transitions
                // to chargebacks and refunds.
                // all other transitions will not happen.
                if (! in_array($statusTo, $allowedList)) {
                    return;
                }
            }
        }

        $statusFrom = ($stateMachine instanceof StateMachineStateEntity) ? $stateMachine->getTechnicalName() : '';

        $addLog = false;

        switch ($statusTo) {
            case MolliePaymentStatus::MOLLIE_PAYMENT_OPEN:
            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAAuthorizedTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
            case MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAPaidTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAFailedTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithACancelledTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithPartialRefundTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithRefundTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK:
                $addLog = true;
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAChargebackTransaction(), $context);
                break;

            default:
                throw new \Exception('Updating Order Status of Order not possible for status: ' . $statusTo);
        }

        if ($addLog) {
            $this->logger->debug(
                'Order status transition of order ' . $order->getOrderNumber() . ' from "' . $statusFrom . '" to "' . $statusTo . '"',
                [
                    'order' => $order->getOrderNumber(),
                    'statusFrom' => $statusFrom,
                    'statusTo' => $statusTo,
                ]
            );
        }
    }
}
