<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class SofortPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::SOFORT;
    public const PAYMENT_METHOD_DESCRIPTION = 'SOFORT Banking';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}