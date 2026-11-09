<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\AutomaticCaptureAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\PaymentLinkAwareInterface;

final class KlarnaPayment extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface, AutomaticCaptureAwareInterface, PaymentLinkAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::KLARNA;
    }

    public function getName(): string
    {
        return 'Klarna';
    }
}
