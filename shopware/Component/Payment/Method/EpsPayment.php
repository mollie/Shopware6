<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class EpsPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::EPS;
    }

    public function getName(): string
    {
        return 'EPS';
    }
}
