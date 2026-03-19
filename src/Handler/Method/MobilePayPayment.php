<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class MobilePayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'mobilepay';
    public const PAYMENT_METHOD_DESCRIPTION = 'MobilePay';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
