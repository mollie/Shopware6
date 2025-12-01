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

    public function getDescription(): string
    {
        return 'Easily accept EPS as a payment method‚ developed by various Austrian banks. Highly popular with Austrian shoppers, this is the main bank transfer method used in Austria.';
    }
}
