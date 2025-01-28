<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;

class MultibancoPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'multibanco';
    public const PAYMENT_METHOD_DESCRIPTION = 'multibanco';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
}
