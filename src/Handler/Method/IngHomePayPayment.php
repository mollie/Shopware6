<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class IngHomePayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::INGHOMEPAY;
    public const PAYMENT_METHOD_DESCRIPTION = 'ING Home\'Pay';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}