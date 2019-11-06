<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class GiroPayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::GIROPAY;
    public const PAYMENT_METHOD_DESCRIPTION = 'Giropay';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}