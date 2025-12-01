<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;

final class KlarnaPayment extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::KLARNA;
    }

    public function getName(): string
    {
        return 'Klarna';
    }

    public function getDescription(): string
    {
        return 'Increase conversion rates, order values, and customer loyalty by offering your customers a range of flexible ways to pay – all through Klarna payments. Combine all Klarna payment options into one seamless method and let your customers pick the payment type and terms they prefer.';
    }
}
