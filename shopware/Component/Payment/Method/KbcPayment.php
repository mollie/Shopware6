<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class KbcPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::KBC;
    }

    public function getName(): string
    {
        return 'KBC/CBC Payment';
    }

    public function getDescription(): string
    {
        return 'Mollie allows you to quickly and easily accept payments through the KBC/CBC Payment Button. It only takes 10 minutes to start receiving KBC/CBC payments with no hidden fees involved‚ as you only pay for successful transactions.';
    }
}
