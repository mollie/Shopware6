<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler;

use Mollie\Shopware\Component\Payment\PaymentHandlerTrait;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;

class PaymentHandler extends AbstractPaymentHandler
{
    use PaymentHandlerTrait;

    public const PAYMENT_SEQUENCE_TYPE_FIRST = 'first';
    public const PAYMENT_SEQUENCE_TYPE_RECURRING = 'recurring';
}
