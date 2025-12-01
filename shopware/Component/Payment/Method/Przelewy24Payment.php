<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class Przelewy24Payment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PRZELEWY24;
    }

    public function getName(): string
    {
        return 'Przelewy24';
    }

    public function getDescription(): string
    {
        return 'The Przelewy24 payment method supports 165 banks and several other payment methods, and is therefore the most popular payment method in Poland. With Mollie you pay these low rates only for successful transactions.';
    }
}
