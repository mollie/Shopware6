<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;

final class FakeManualCaptureModeAwarePaymentHandler extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface
{
    public function __construct(
    ) {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'Fake Manual Capture Mode Handler';
    }
}
