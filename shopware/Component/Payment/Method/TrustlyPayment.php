<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class TrustlyPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::TRUSTLY;
    }

    public function getName(): string
    {
        return 'Trustly';
    }

    public function getDescription(): string
    {
        return 'A global leader in Open Banking, the Trustly payment method is an account-to-account payment platform that lets consumers pay businesses directly from their bank accounts. It’s a fast, secure payment method that benefits both businesses and consumers.';
    }
}
