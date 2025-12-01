<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class BanContactPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BAN_CONTACT;
    }

    public function getName(): string
    {
        return 'Bancontact';
    }

    public function getDescription(): string
    {
        return 'Bancontact processes about 150‚000 transactions a day‚ making it the most used payment method in Belgium. With Mollie you only pay a set‚ low fee for every successful transaction with the Bancontact payment method.';
    }
}
