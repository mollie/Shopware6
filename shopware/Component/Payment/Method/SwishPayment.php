<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class SwishPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::SWISH;
    }

    public function getName(): string
    {
        return 'Swish';
    }

    public function getDescription(): string
    {
        return 'Swish payments has over 8 million users and is a member of the European Mobile Payment Systems Association. The Swish payment method offers convenience by allowing customers to authenticate and approve payments using the Swish mobile app and the Swedish BankID mobile app.';
    }
}
