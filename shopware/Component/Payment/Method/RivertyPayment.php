<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\PaymentLinkAwareInterface;

final class RivertyPayment extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface, PaymentLinkAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::RIVERTY;
    }

    public function getName(): string
    {
        return 'Riverty';
    }
}
