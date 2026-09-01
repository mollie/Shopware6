<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\TestOnlyAwareInterface;

final class FakeTestOnlyPaymentHandler extends AbstractMolliePaymentHandler implements TestOnlyAwareInterface
{
    public function __construct(private readonly PaymentMethod $paymentMethod = PaymentMethod::BLIK)
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getName(): string
    {
        return 'Test only payment method';
    }
}
