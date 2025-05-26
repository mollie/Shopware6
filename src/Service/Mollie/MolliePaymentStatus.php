<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mollie;

class MolliePaymentStatus
{
    public const MOLLIE_PAYMENT_UNKNOWN = 'unknown';

    public const MOLLIE_PAYMENT_COMPLETED = 'completed';
    public const MOLLIE_PAYMENT_PAID = 'paid';
    public const MOLLIE_PAYMENT_AUTHORIZED = 'authorized';
    public const MOLLIE_PAYMENT_PENDING = 'pending';
    public const MOLLIE_PAYMENT_OPEN = 'open';
    public const MOLLIE_PAYMENT_CANCELED = 'canceled';
    public const MOLLIE_PAYMENT_EXPIRED = 'expired';
    public const MOLLIE_PAYMENT_FAILED = 'failed';

    /**
     * attention!
     * mollie has no status for refunds. its always "paid", but
     * the payment itself has additional refund keys and values.
     * we still need a status for order transitions and more due to
     * the plugin architecture. so we've added our fictional status entries here!
     * these will never come from the mollie API
     */
    public const MOLLIE_PAYMENT_REFUNDED = 'refunded';
    public const MOLLIE_PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';
    public const MOLLIE_PAYMENT_CHARGEBACK = 'chargeback';

    /**
     * Gets if the provided payment status means that
     * it's allowed to cancel an (open) order.
     *
     * @param string $paymentIdentifier
     * @param string $status
     *
     * @return bool
     */
    public static function isFailedStatus($paymentIdentifier, $status)
    {
        $list = [
            MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED,
            MolliePaymentStatus::MOLLIE_PAYMENT_FAILED,
            MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED,
        ];

        return in_array($status, $list, true);
    }

    /**
     * Gets if the provided payment status is an approved payment.
     * This means that the order is approved.
     * This does not mean that its already completely paid.
     *
     * @param string $status
     *
     * @return bool
     */
    public static function isApprovedStatus($status)
    {
        $list = [
            MolliePaymentStatus::MOLLIE_PAYMENT_OPEN,
            MolliePaymentStatus::MOLLIE_PAYMENT_PAID,
            MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED,
            MolliePaymentStatus::MOLLIE_PAYMENT_PENDING,
        ];

        return in_array($status, $list, true);
    }
}
