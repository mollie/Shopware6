<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class PayconiqPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'payconiq';
    public const PAYMENT_METHOD_DESCRIPTION = 'Payconiq';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

}