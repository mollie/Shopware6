<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\PaymentLinkAwareInterface;

final class FakePaymentLinkAwarePaymentHandler extends AbstractMolliePaymentHandler implements PaymentLinkAwareInterface
{
    public function __construct(private readonly PaymentMethod $paymentMethod = PaymentMethod::PAYPAL)
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getName(): string
    {
        return 'Fake payment link payment method';
    }
}
