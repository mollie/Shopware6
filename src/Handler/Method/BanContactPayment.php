<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class BanContactPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANCONTACT;
    public const PAYMENT_METHOD_DESCRIPTION = 'Bancontact';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}