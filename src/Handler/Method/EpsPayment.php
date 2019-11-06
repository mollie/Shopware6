<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class EpsPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::EPS;
    public const PAYMENT_METHOD_DESCRIPTION = 'eps';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}