<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class iDealPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::IDEAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'iDEAL';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}