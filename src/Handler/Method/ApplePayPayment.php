<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class ApplePayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::APPLEPAY;
    public const PAYMENT_METHOD_DESCRIPTION = 'Apple Pay';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}