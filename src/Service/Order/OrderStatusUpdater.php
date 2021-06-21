<?php

namespace Kiener\MolliePayments\Service\Order;


use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

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
     *
     */
    private const STATE_REFUNDED = 'refunded';

    /**
     *
     */
    private const STATE_REFUNDED_PARTIALLY = 'refunded_partially';


    /**
     * @param OrderTransactionStateHandler $transitionHandler
     * @param OrderStateService $orderHandler
     * @param StateMachineRegistry $stateMachineRegistry
     */
    public function __construct(OrderTransactionStateHandler $transitionHandler, OrderStateService $orderHandler, StateMachineRegistry $stateMachineRegistry)
    {
        $this->transitionHandler = $transitionHandler;
        $this->orderHandler = $orderHandler;
        $this->stateMachineRegistry = $stateMachineRegistry;
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

        $currentStatus = $transactionState->getTechnicalName();

        switch ($status) {

            case MolliePaymentStatus::MOLLIE_PAYMENT_OPEN:
                if ($currentStatus !== PaymentStatus::STATUS_OPEN) {
                    $this->transitionHandler->reopen($transaction->getId(), $context);
                }
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                {
                    if (defined('Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_AUTHORIZE')) {
                        $transitionTarget = PaymentStatus::STATUS_AUTHORIZED;
                        $transitionAction = StateMachineTransitionActions::ACTION_AUTHORIZE;
                    } else {
                        $transitionTarget = PaymentStatus::STATUS_PAID;
                        $transitionAction = StateMachineTransitionActions::ACTION_PAY;
                    }

                    if ($currentStatus !== $transitionTarget) {
                        $transition = new Transition(OrderTransactionDefinition::ENTITY_NAME, $transaction->getId(), $transitionAction, 'stateId');

                        $this->stateMachineRegistry->transition($transition, $context);
                    }
                }
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
            case MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                {
                    if ($currentStatus !== PaymentStatus::STATUS_PAID) {
                        if (method_exists($this->transitionHandler, 'paid')) {
                            $this->transitionHandler->paid($transaction->getId(), $context);
                        } else {
                            $this->transitionHandler->pay($transaction->getId(), $context);
                        }
                    }
                }
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                if ($currentStatus !== PaymentStatus::STATUS_CANCELED) {
                    $this->transitionHandler->cancel($transaction->getId(), $context);
                }
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
                {
                    if (method_exists($this->transitionHandler, 'fail')) {
                        if ($currentStatus !== PaymentStatus::STATUS_FAILED) {
                            $this->transitionHandler->fail($transaction->getId(), $context);
                        }
                    } else {
                        if ($currentStatus !== PaymentStatus::STATUS_CANCELED) {
                            $this->transitionHandler->cancel($transaction->getId(), $context);
                        }
                    }
                }
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
                $transitionTarget = self::STATE_REFUNDED;
                if ($currentStatus !== $transitionTarget) {
                    $this->transitionHandler->refund($transaction->getId(), $context);
                }
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $transitionTarget = self::STATE_REFUNDED_PARTIALLY;
                if ($currentStatus !== $transitionTarget) {
                    $this->transitionHandler->refundPartially($transaction->getId(), $context);
                }
                break;

            default:
                throw new \Exception('Updating Payment Status of Order not possible for status: ' . $status);
        }
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
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithACancelledTransaction(), $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
                $this->orderHandler->setOrderState($order, $settings->getOrderStateWithAFailedTransaction(), $context);
                break;

            default:
                throw new \Exception('Updating Order Status of Order not possible for status: ' . $status);
        }
    }

}
