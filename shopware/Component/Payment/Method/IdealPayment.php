<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class IdealPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::IDEAL;
    }

    public function getName(): string
    {
        return 'iDEAL';
    }

    public function getDescription(): string
    {
        return 'Selling in the Netherlands? Then you need to accept iDEAL payments. This leading Dutch bank transfer method provides real-time, secure payments with lower fees than credit cards. Your customers get a seamless checkout experience through their banking platforms, boosting trust and increasing conversion. You get fast settlement times and guaranteed payments for healthier cash flows.';
    }
}
