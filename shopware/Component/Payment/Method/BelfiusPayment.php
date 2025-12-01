<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class BelfiusPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BELFIUS;
    }

    public function getName(): string
    {
        return 'Belfius';
    }

    public function getDescription(): string
    {
        return 'With Mollie‚ you can easily and quickly accept payments with the Belfius Pay Button‚ the online payment method of one of Belgium’s biggest banks. It only takes 10 minutes to start receiving Belfius payments and there are no hidden fees involved‚ you only pay for successful transactions.';
    }
}
