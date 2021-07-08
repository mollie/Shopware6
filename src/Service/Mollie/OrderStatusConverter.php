<?php

namespace Kiener\MolliePayments\Service\Mollie;

use Mollie\Api\Resources\Order;


class OrderStatusConverter
{

    /**
     * @param Order $order
     * @return string
     */
    public function getMollieStatus(Order $order): string
    {
        $targetStatus = $this->getLatestPaymentStatus($order);

        # if we do not have a payment status
        # then try to get a status from the order object of Mollie
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

        # i dont know if that can happen in both ways?
        # but it was definitely necessary to add it
        if ($this->isOrderFullyRefunded($order)) {
            $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED;
        }

        if ($this->isOrderPartiallyRefunded($order)) {
            $targetStatus = MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED;
        }

        return $targetStatus;
    }


    /**
     * @param null $mollieOrder
     * @return string
     */
    private function getLatestPaymentStatus($mollieOrder = null): string
    {
        if (!$mollieOrder instanceof Order) {
            return '';
        }


        $latestPayment = null;

        foreach ($mollieOrder->payments() as $payment) {

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


        if ($latestPayment === null) {
            return MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN;
        }

        $status = MolliePaymentStatus::MOLLIE_PAYMENT_UNKNOWN;

        if ($latestPayment->isPaid()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_PAID;
        } elseif ($latestPayment->isAuthorized()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED;
        } else if ($latestPayment->isPending()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_PENDING;
        } elseif ($latestPayment->isOpen()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_OPEN;
        } elseif ($latestPayment->isCanceled()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED;
        } elseif ($latestPayment->isFailed()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_FAILED;
        } elseif ($latestPayment->isExpired()) {
            $status = MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED;
        }

        return $status;
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

        # both of them are strings, but that's totally fine
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

        # both of them are strings, but that's totally fine
        return ($orderValue !== $refundedValue);
    }

}
