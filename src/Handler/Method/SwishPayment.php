<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class SwishPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'swish';
    public const PAYMENT_METHOD_DESCRIPTION = 'Swish';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
