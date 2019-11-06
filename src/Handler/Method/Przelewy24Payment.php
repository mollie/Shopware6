<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class Przelewy24Payment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PRZELEWY24;
    public const PAYMENT_METHOD_DESCRIPTION = 'Przelewy24';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}