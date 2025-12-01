<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class TwintPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::TWINT;
    }

    public function getName(): string
    {
        return 'TWINT';
    }

    public function getDescription(): string
    {
        return 'TWINT is the most popular mobile payment method in Switzerland. It lets users connect their bank accounts or cards to the Twint app for secure payments using their smartphones.';
    }
}
