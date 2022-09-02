<?php

namespace Kiener\MolliePayments\Service\Mollie;

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;

class OrderStatusConverter
{
    /**
     * @param Order $order
     * @return string
     */
    public function getMollieOrderStatus(Order $order): string
    {
        $payment = $this->getLatestPayment($order);
        $targetStatus = $this->getMolliePaymentStatus($payment);

        // If the payment has been charged back, return immediately
        // to prevent status from being changed if order has been refunded.
        if ($targetStatus === MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK) {
            return $targetStatus;
        }

        // if we do not have a payment status
        // then try to get a status from the order object of Mollie
        if ($targetStatus === MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN) {
            if ($order->isPaid()) {
                $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_PAID;
            } elseif ($order->isPending()) {
                $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_PENDING;
            } elseif ($order->isAuthorized()) {
                $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED;
            } elseif ($order->isCanceled()) {
                $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED;
            } elseif ($order->isCompleted()) {
                $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED;
            } elseif ($order->isExpired()) {
                $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED;
            }
        }

        // I don't know if that can happen in both ways?
        // but it was definitely necessary to add it
        if ($this->isOrderFullyRefunded($order)) {
            $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED;
        }

        if ($this->isOrderPartiallyRefunded($order)) {
            $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED;
        }

        return $targetStatus;
    }

    /**
     * @param null|Payment $payment
     * @return string
     */
    public function getMolliePaymentStatus(?Payment $payment = null): string
    {
        if ($payment === null) {
            return MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN;
        }

        if ($payment->amountChargedBack && $payment->amountChargedBack->value > 0) {
            return MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK;
        }

        $status = MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN;

        if ($payment->isPaid()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_PAID;
        } elseif ($payment->isAuthorized()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED;
        } elseif ($payment->isPending()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_PENDING;
        } elseif ($payment->isOpen()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_OPEN;
        } elseif ($payment->isCanceled()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED;
        } elseif ($payment->isFailed()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_FAILED;
        } elseif ($payment->isExpired()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED;
        }


        return $status;
    }

    /**
     * @TODO we should move this somewhere else, but i had to (re)use this for now - so it's now public
     * @param Order $order
     * @return null|Payment
     */
    public function getLatestPayment(Order $order): ?Payment
    {
        $latestPayment = null;

        $payments = $order->payments();

        if (!$payments instanceof PaymentCollection) {
            return null;
        }

        foreach ($payments as $payment) {
            $currentCreated = strtotime($payment->createdAt);

            if ($latestPayment === null) {
                $latestPayment = $payment;
                continue;
            }

            $latestCreated = strtotime($latestPayment->createdAt);

            if ($currentCreated > $latestCreated) {
                $latestPayment = $payment;
            }
        }

        return $latestPayment;
    }

    /**
     * Gets if the provided order is fully refunded.
     *
     * @param Order $order
     * @return bool
     */
    private function isOrderFullyRefunded(Order $order): bool
    {
        if ($order->amountRefunded === null) {
            return false;
        }

        $orderValue = $order->amount->value;
        $refundedValue = $order->amountRefunded->value;

        // both of them are strings, but that's totally fine
        return ($orderValue === $refundedValue);
    }

    /**
     * Gets if the provided order is partially refunded.
     *
     * @param Order $order
     * @return bool
     */
    private function isOrderPartiallyRefunded(Order $order): bool
    {
        if ($order->amountRefunded === null) {
            return false;
        }

        $orderValue = $order->amount->value;
        $refundedValue = $order->amountRefunded->value;

        // both of them are strings, but that's totally fine
        return ($orderValue !== $refundedValue);
    }
}
