<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class TrustlyPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'trustly';
    public const PAYMENT_METHOD_DESCRIPTION = 'Trustly';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
