<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class MbWayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'mbway';
    public const PAYMENT_METHOD_DESCRIPTION = 'MB Way';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
