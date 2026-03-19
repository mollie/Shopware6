<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class VippsPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'vipps';
    public const PAYMENT_METHOD_DESCRIPTION = 'Vipps';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
