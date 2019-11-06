<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class KlarnaPayLaterPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::KLARNA_PAY_LATER;
    public const PAYMENT_METHOD_DESCRIPTION = 'Pay later.';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}