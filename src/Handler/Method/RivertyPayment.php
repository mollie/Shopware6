<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;


class RivertyPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'riverty';
    public const PAYMENT_METHOD_DESCRIPTION = 'Riverty';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}