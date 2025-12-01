<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class GiftCardPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::GIFT_CARD;
    }

    public function getName(): string
    {
        return 'Gift cards';
    }

    public function getDescription(): string
    {
        return 'Mollie allows you to quickly and easily accepts payment by gift card from the most used suppliers. It only takes 10 minutes to start receiving gift card payments with no hidden fees involved‚ you only pay for successful transactions.';
    }
}
