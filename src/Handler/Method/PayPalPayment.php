<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class PayPalPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PAYPAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'PayPal';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}