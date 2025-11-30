<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class PayPalPayment extends AbstractMolliePaymentHandler
{
    protected PaymentMethod $method = PaymentMethod::PAYPAL;
}
