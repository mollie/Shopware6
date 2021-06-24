<?php

namespace Kiener\MolliePayments\Service\Mollie;


class MolliePaymentStatus
{
    const MOLLIE_PAYMENT_UNKNOWN = 'unknown';

    const MOLLIE_PAYMENT_COMPLETED = 'completed';
    const MOLLIE_PAYMENT_PAID = 'paid';
    const MOLLIE_PAYMENT_AUTHORIZED = 'authorized';
    const MOLLIE_PAYMENT_PENDING = 'pending';
    const MOLLIE_PAYMENT_OPEN = 'open';
    const MOLLIE_PAYMENT_CANCELED = 'canceled';
    const MOLLIE_PAYMENT_EXPIRED = 'expired';
    const MOLLIE_PAYMENT_FAILED = 'failed';

    /**
     * attention!
     * mollie has no status for refunds. its always "paid", but
     * the payment itself has additional refund keys and values.
     * we still need a status for order transitions and more due to
     * the plugin architecture. so we've added our fictional status entries here!
     * these will never come from the mollie API
     */
    const MOLLIE_PAYMENT_REFUNDED = 'refunded';
    const MOLLIE_PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';


    /**
     * Gets if the provided payment status means that
     * its allowed to cancel an (open) order.
     *
     * @param $status
     * @return bool
     */
    public static function isFailedStatus($status)
    {
        $list = [
            MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED,
            MolliePaymentStatus::MOLLIE_PAYMENT_FAILED,
            MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED,
        ];

        return (in_array($status, $list, true));
    }

    /**
     * Gets if the provided payment status is an approved payment.
     * This means that the order is approved.
     * This does not mean that its already completely paid.
     *
     * @param $status
     * @return bool
     */
    public static function isApprovedStatus($status)
    {
        $list = [
            MolliePaymentStatus::MOLLIE_PAYMENT_PAID,
            MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED,
            MolliePaymentStatus::MOLLIE_PAYMENT_PENDING
        ];

        return (in_array($status, $list, true));
    }

}
