<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;

final class FakeDeprecatedPaymentHandler extends AbstractMolliePaymentHandler implements DeprecatedMethodAwareInterface
{
    public function __construct(private readonly PaymentMethod $paymentMethod = PaymentMethod::BELFIUS)
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getName(): string
    {
        return 'Deprecated payment method';
    }
}
