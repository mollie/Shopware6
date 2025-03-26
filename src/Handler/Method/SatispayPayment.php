<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class SatispayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'satispay';
    public const PAYMENT_METHOD_DESCRIPTION = 'Satispay';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
