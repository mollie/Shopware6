<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;

class OrderExpireService
{

    /**
     * @var OrderStatusUpdater
     */
    private $orderStatusUpdater;

    /**
     * @var OrderTimeService
     */
    private $orderTimeService;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderStatusUpdater $orderStatusUpdater,
        OrderTimeService   $orderTimeService,
        SettingsService    $settingsService,
        LoggerInterface $logger
    ) {
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->orderTimeService = $orderTimeService;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    /**
     * If an order is "in progress" but the payment link is already expired, the order is changed to cannceled
     * @param OrderCollection $orders
     * @param Context $context
     * @return int
     */
    public function cancelExpiredOrders(OrderCollection $orders, Context $context): int
    {
        $resetted = 0;
        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            if (! $order instanceof OrderEntity) {
                continue;
            }

            $orderAttributes = new OrderAttributes($order);

            if (strlen($orderAttributes->getMollieOrderId()) === 0) {
                continue;
            }

            $transactions = $order->getTransactions();

            if ($transactions === null || $transactions->count() === 0) {
                continue;
            }

            $transactions->sort(function (OrderTransactionEntity $a, OrderTransactionEntity $b) {
                if ($a->getCreatedAt() === null || $b->getCreatedAt() === null) {
                    return -1;
                }
                return $a->getCreatedAt()->getTimestamp() <=> $b->getCreatedAt()->getTimestamp();
            });

            /** @var OrderTransactionEntity $lastTransaction */
            $lastTransaction = $transactions->last();

            $paymentMethod = $lastTransaction->getPaymentMethod();
            if ($paymentMethod === null) {
                $this->logger->warning('Transaction has no payment method', ['orderNumber'=>$order->getOrderNumber(),'transactionId'=>$lastTransaction->getId()]);
                continue;
            }
            $paymentMethodIdentifier = $paymentMethod->getHandlerIdentifier();

            if (strpos($paymentMethodIdentifier, 'Mollie') === false) {
                $this->logger->debug('Payment method is not a mollie payment, dont touch it', ['identifier'=>$paymentMethodIdentifier]);
                continue;
            }

            $stateMachineState = $lastTransaction->getStateMachineState();
            if ($stateMachineState === null) {
                continue;
            }

            $lastStatus = $stateMachineState->getTechnicalName();

            // disregard any orders that are not in progress
            if ($lastStatus !== OrderStates::STATE_IN_PROGRESS) {
                continue;
            }

            $settings = $this->settingsService->getSettings();
            $finalizeTransactionTimeInMinutes = $settings->getPaymentFinalizeTransactionTime();

            if ($this->orderUsesSepaPayment($lastTransaction)) {
                $bankTransferDueDays = $settings->getPaymentMethodBankTransferDueDateDays();
                if ($bankTransferDueDays === null) {
                    $bankTransferDueDays = BankTransferPayment::DUE_DATE_MIN_DAYS;
                }
                $finalizeTransactionTimeInMinutes = 60 * 60 * 24 * $bankTransferDueDays;
            }

            if ($this->orderTimeService->isOrderAgeGreaterThan($order, $finalizeTransactionTimeInMinutes) === false) {
                continue;
            }

            // orderStatusUpdater needs the order to be set on the transaction
            $lastTransaction->setOrder($order);

            // this forces the order to be open again
            $context->addState(OrderStatusUpdater::ORDER_STATE_FORCE_OPEN);

            try {
                $this->orderStatusUpdater->updatePaymentStatus($lastTransaction, MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED, $context);
                $resetted++;
            } catch (\Exception $exception) {
                $this->logger->error('Failed to update payment status for transaction', [
                    'transaction' => $lastTransaction->getId(),
                    'order' => $order->getOrderNumber()
                ]);
            }
        }

        return $resetted;
    }

    /**
     * @param OrderTransactionEntity $transaction
     * @return bool
     * @todo refactor once php8.0 is minimum version. Use Null-safe operator
     */
    private function orderUsesSepaPayment(OrderTransactionEntity $transaction): bool
    {
        $paymentMethod = $transaction->getPaymentMethod();

        if ($paymentMethod === null) {
            return false;
        }

        return $paymentMethod->getHandlerIdentifier() === BankTransferPayment::class;
    }
}
