<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class In3Payment extends PaymentHandler
{
    /**
     *
     */
    public const PAYMENT_METHOD_NAME = 'in3'; # not yet in API  PaymentMethod::IDEAL;

    /**
     *
     */
    public const PAYMENT_METHOD_DESCRIPTION = 'in3';

    /**
     * @var string
     */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}
