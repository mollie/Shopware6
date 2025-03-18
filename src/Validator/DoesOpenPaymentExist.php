<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Validator;

use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;

class DoesOpenPaymentExist
{
    /**
     * @param Payment[] $payments
     */
    public static function validate(array $payments): bool
    {
        if (count($payments) === 0) {
            return false;
        }

        $filteredPayments = array_filter($payments, static function (Payment $payment) {
            return $payment->status === PaymentStatus::STATUS_OPEN;
        });

        return count($filteredPayments) > 0;
    }
}
