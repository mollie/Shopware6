<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class In3Payment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::IN3;

    public const PAYMENT_METHOD_DESCRIPTION = 'iDeal IN3';


    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
