<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class GiftCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::GIFTCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Gift cards';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}