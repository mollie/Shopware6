<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class BelfiusPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BELFIUS;
    public const PAYMENT_METHOD_DESCRIPTION = 'Belfius';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}