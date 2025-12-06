<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;

final class IdealPayment extends AbstractMolliePaymentHandler implements SubscriptionAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::IDEAL;
    }

    public function getName(): string
    {
        return 'iDEAL';
    }
}
