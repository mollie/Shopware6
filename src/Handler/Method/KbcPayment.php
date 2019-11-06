<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class KbcPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::KBC;
    public const PAYMENT_METHOD_DESCRIPTION = 'KBC/CBC Payment Button';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}