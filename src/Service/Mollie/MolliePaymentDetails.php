<?php

namespace Kiener\MolliePayments\Service\Mollie;

use Mollie\Api\Resources\Payment;

class MolliePaymentDetails
{
    /**
     * @param null|Payment $payment
     * @return string
     */
    public function getMandateId(?Payment $payment): string
    {
        if (!$payment instanceof Payment) {
            return '';
        }

        if (!isset($payment->mandateId)) {
            return '';
        }

        return (string)$payment->mandateId;
    }
}
