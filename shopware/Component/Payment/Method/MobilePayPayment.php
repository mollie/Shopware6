<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\AutomaticCaptureAwareInterface;

final class MobilePayPayment extends AbstractMolliePaymentHandler implements AutomaticCaptureAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::MOBILE_PAY;
    }

    public function getName(): string
    {
        return 'MobilePay';
    }
}
