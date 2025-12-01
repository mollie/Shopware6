<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;

final class RivertyPayment extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::RIVERTY;
    }

    public function getName(): string
    {
        return 'Riverty';
    }

    public function getDescription(): string
    {
        return 'With Riverty, your customers enjoy the flexibility of a 30-day payment delay, allowing them to receive and even try their products before paying. Meanwhile, you, the merchant, get paid upfront and in full by Mollie. Riverty handles all the payment processing, including financial risk and collections, so you can focus on growing your business without any hassle.';
    }
}
