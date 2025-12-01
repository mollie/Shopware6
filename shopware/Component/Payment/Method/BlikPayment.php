<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class BlikPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BLIK;
    }

    public function getName(): string
    {
        return 'Blik';
    }

    public function getDescription(): string
    {
        return "Poland's most popular payment method, BLIK allows users to link their bank accounts or payment cards to the BLIK app to pay online. The BLIK payment method provides a quick and convenient way to make payments, transfers, and purchases using a mobile phone.";
    }
}
