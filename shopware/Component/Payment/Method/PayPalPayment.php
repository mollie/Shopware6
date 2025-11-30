<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class PayPalPayment extends AbstractMolliePaymentHandler
{
    protected string $method = 'paypal';
}
