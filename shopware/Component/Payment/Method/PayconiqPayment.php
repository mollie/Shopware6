<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;

final class PayconiqPayment extends AbstractMolliePaymentHandler implements DeprecatedMethodAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYCONIQ;
    }

    public function getName(): string
    {
        return 'Payconiq';
    }
}
