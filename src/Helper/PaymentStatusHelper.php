<?php


namespace Kiener\MolliePayments\Helper;

use Exception;
use Kiener\MolliePayments\Service\LoggerService;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class PaymentStatusHelper
{
    /** @var LoggerService */
    protected $logger;

    /** @var OrderTransactionStateHandler */
    protected $orderTransactionStateHandler;

    /** @var StateMachineRegistry */
    protected $stateMachineRegistry;

    /**
     * PaymentStatusHelper constructor.
     *
     * @param LoggerService                $logger
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param StateMachineRegistry         $stateMachineRegistry
     */
    public function __construct(
        LoggerService $logger,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        StateMachineRegistry $stateMachineRegistry
    )
    {
        $this->logger = $logger;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * Order transaction state handler.
     *
     * @return OrderTransactionStateHandler
     */
    public function getOrderTransactionStateHandler(): OrderTransactionStateHandler
    {
        return $this->orderTransactionStateHandler;
    }

    /**
     * Processes the payment status for a Mollie Order. Uses the transaction state handler
     * to handle the transaction to a new status.
     *
     * @param OrderTransactionEntity $transaction
     * @param OrderEntity            $order
     * @param Order                  $mollieOrder
     * @param Context                $context
     *
     * @return string
     */
    public function processPaymentStatus(
        OrderTransactionEntity $transaction,
        OrderEntity $order,
        Order $mollieOrder,
        Context $context
    ): string
    {
        $paidNumber = 0;
        $authorizedNumber = 0;
        $cancelledNumber = 0;
        $expiredNumber = 0;
        $failedNumber = 0;
        $pendingNumber = 0;
        $payments = $mollieOrder->payments();
        $paymentsTotal = $payments !== null ? $payments->count() : 0;
        $transactionState = $transaction->getStateMachineState();

        /**
         * We gather the states for all payments in order to handle
         * the states of all payments in this order.
         */
        if ($payments !== null && $payments->count() > 0) {
            /** @var Payment $payment */
            foreach ($payments as $payment) {
                if ($payment->isPaid()) {
                    $paidNumber++;
                }

                if ($payment->isCanceled()) {
                    $cancelledNumber++;
                }

                if ($payment->isExpired()) {
                    $expiredNumber++;
                }

                if ($payment->isFailed()) {
                    $failedNumber++;
                }

                if ($payment->isPending()) {
                    $pendingNumber++;
                }

                if ($payment->isAuthorized()) {
                    $authorizedNumber++;
                }
            }
        }

        /**
         * The order is paid.
         */
        if (
            $transactionState !== null
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_PAID
            && $mollieOrder->isPaid()
        ) {
            try {
                if (method_exists($this->orderTransactionStateHandler, 'paid')) {
                    $this->orderTransactionStateHandler->paid($transaction->getId(), $context);
                } else {
                    $this->orderTransactionStateHandler->pay($transaction->getId(), $context);
                }
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-set-transaction-state'
                    ]
                );
            }

            return PaymentStatus::STATUS_PAID;
        }

        /**
         * The order is cancelled.
         */
        if (
            $transactionState !== null
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_CANCELED
            && $mollieOrder->isCanceled()
        ) {
            try {
                $this->orderTransactionStateHandler->cancel($transaction->getId(), $context);
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-set-transaction-state'
                    ]
                );
            }

            return PaymentStatus::STATUS_CANCELED;
        }

        /**
         * All payments are authorized, therefore the order payment is authorized. We
         * transition to paid as Shopware 6 has no transition to a authorized state (yet).
         */
        if (
            $authorizedNumber > 0
            && $authorizedNumber === $paymentsTotal
        ) {
            return PaymentStatus::STATUS_AUTHORIZED;
        }

        /**
         * All payments are cancelled, therefore the order payment is canceled.
         */
        if (
            $cancelledNumber > 0
            && $cancelledNumber === $paymentsTotal
            && $transactionState !== null
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_CANCELED
        ) {
            try {
                $this->orderTransactionStateHandler->cancel($transaction->getId(), $context);
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-set-transaction-state'
                    ]
                );
            }

            return PaymentStatus::STATUS_CANCELED;
        }

        /**
         * All payments expired, therefore the order payment expired. We transition
         * to cancelled as Shopware 6 has no transition to a expired state (yet).
         */
        if (
            $expiredNumber > 0
            && $expiredNumber === $paymentsTotal
            && $transactionState !== null
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_CANCELED
        ) {
            try {
                $this->orderTransactionStateHandler->cancel($transaction->getId(), $context);
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-set-transaction-state'
                    ]
                );
            }

            return PaymentStatus::STATUS_CANCELED;
        }

        /**
         * All payments failed, therefore the order payment failed. We transition
         * to cancelled as Shopware 6 has no transition to a failed state (yet).
         */
        if (
            $failedNumber > 0
            && $failedNumber === $paymentsTotal
            && $transactionState !== null
            && (
                $transactionState->getTechnicalName() !== PaymentStatus::STATUS_FAILED
                && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_CANCELED
            )
        ) {
            try {
                if (method_exists($this->orderTransactionStateHandler, 'fail')) {
                    $this->orderTransactionStateHandler->fail($transaction->getId(), $context);
                } else {
                    $this->orderTransactionStateHandler->cancel($transaction->getId(), $context);
                }
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-set-transaction-state'
                    ]
                );
            }

            return PaymentStatus::STATUS_FAILED;
        }

        /**
         * All payments are pending, therefore the order payment is pending. We
         * transition to paid as Shopware 6 has no transition to a pending state (yet).
         */
        if (
            $pendingNumber > 0
            && $pendingNumber === $paymentsTotal
        ) {
            return PaymentStatus::STATUS_PAID;
        }

        /**
         * The paid amount is equal to the total amount, therefore the order is paid.
         */
        if (
            $paidNumber > 0
            && $paidNumber === $order->getAmountTotal()
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_PAID
        ) {
            try {
                if (method_exists($this->orderTransactionStateHandler, 'paid')) {
                    $this->orderTransactionStateHandler->paid($transaction->getId(), $context);
                } else {
                    $this->orderTransactionStateHandler->pay($transaction->getId(), $context);
                }
            } catch (Exception $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $context,
                    $e,
                    [
                        'function' => 'payment-set-transaction-state'
                    ]
                );
            }

            return PaymentStatus::STATUS_PAID;
        }

        return PaymentStatus::STATUS_OPEN;
    }
}