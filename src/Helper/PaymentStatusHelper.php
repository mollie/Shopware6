<?php

namespace Kiener\MolliePayments\Helper;

use Exception;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class PaymentStatusHelper
{
    /** @var LoggerService */
    protected $logger;

    /** @var OrderStateHelper */
    protected $orderStateHelper;

    /** @var OrderTransactionStateHandler */
    protected $orderTransactionStateHandler;

    /** @var SettingsService */
    protected $settingsService;

    /** @var StateMachineRegistry */
    protected $stateMachineRegistry;

    /** @var EntityRepositoryInterface */
    protected $paymentMethodRepository;

    /** @var EntityRepositoryInterface */
    protected $orderTransactionRepository;

    /**
     * PaymentStatusHelper constructor.
     *
     * @param LoggerService $logger
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param SettingsService $settingsService
     * @param StateMachineRegistry $stateMachineRegistry
     */
    public function __construct(
        LoggerService $logger,
        OrderStateHelper $orderStateHelper,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        SettingsService $settingsService,
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $orderTransactionRepository
    )
    {
        $this->logger = $logger;
        $this->orderStateHelper = $orderStateHelper;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->settingsService = $settingsService;
        $this->stateMachineRegistry = $stateMachineRegistry;

        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
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
     * @param OrderEntity $order
     * @param Order $mollieOrder
     * @param Context $context
     * @param string|null $salesChannelId
     *
     * @return string
     */
    public function processPaymentStatus(
        OrderTransactionEntity $transaction,
        OrderEntity $order,
        Order $mollieOrder,
        Context $context,
        ?string $salesChannelId = null
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

        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $salesChannelId,
            $context
        );

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
         * Correct the payment method if a different one was selected in mollie
         */
        try {
            $molliePaymentMethodId = $this->paymentMethodRepository->searchIds(
                (new Criteria())
                    ->addFilter(new EqualsFilter('customFields.mollie_payment_method_name', $mollieOrder->method)),
                $context
            )->firstId();

            if (!is_null($molliePaymentMethodId) && $molliePaymentMethodId !== $transaction->getPaymentMethodId()) {
                $transaction->setPaymentMethodId($molliePaymentMethodId);

                $this->orderTransactionRepository->update([
                    [
                        'id' => $transaction->getUniqueIdentifier(),
                        'paymentMethodId' => $molliePaymentMethodId
                    ]
                ],
                    $context);
            }
        } catch (\Throwable $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context,
                $e,
                [
                    'function' => 'payment-set-transaction-method'
                ]
            );
        }

        /**
         * The order is paid.
         */
        if (
            $transactionState !== null
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_PAID
            // FIXME: Should probably check against OrderTransactionStates constants here
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

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithAPaidTransaction(), $context);

            return PaymentStatus::STATUS_PAID;
        }

        /**
         * The order is cancelled.
         */
        if (
            $transactionState !== null
            && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_CANCELED
            // FIXME: Should probably check against OrderTransactionStates constants here
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

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithACancelledTransaction(), $context);

            return PaymentStatus::STATUS_CANCELED;
        }

        if ($mollieOrder->isAuthorized()) {
            // FIXME: Should probably check against OrderTransactionStates constants here
            if ($transactionState !== null && $transactionState->getTechnicalName() !== PaymentStatus::STATUS_AUTHORIZED) {
                try {
                    $this->stateMachineRegistry->transition(
                        new Transition(
                            OrderTransactionDefinition::ENTITY_NAME,
                            $transaction->getId(),
                            StateMachineTransitionActions::ACTION_AUTHORIZE,
                            'stateId'
                        ),
                        $context
                    );
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
            }

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithAAuthorizedTransaction(), $context);
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
            // FIXME: Should probably check against OrderTransactionStates constants here
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

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithACancelledTransaction(), $context);

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
            // FIXME: Should probably check against OrderTransactionStates constants here
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

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithACancelledTransaction(), $context);

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
                // FIXME: Should probably check against OrderTransactionStates constants here
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

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithAFailedTransaction(), $context);

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
            // FIXME: Should probably check against OrderTransactionStates constants here
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

            // Process the order state automation
            $this->orderStateHelper->setOrderState($order, $settings->getOrderStateWithAPaidTransaction(), $context);

            return PaymentStatus::STATUS_PAID;
        }

        return PaymentStatus::STATUS_OPEN;
    }
}
