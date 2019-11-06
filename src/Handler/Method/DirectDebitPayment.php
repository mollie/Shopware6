<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class DirectDebitPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::DIRECTDEBIT;
    public const PAYMENT_METHOD_DESCRIPTION = 'SEPA Direct Debit';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}