<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\PaymentLinkAwareInterface;

final class VoucherPayment extends AbstractMolliePaymentHandler implements PaymentLinkAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::VOUCHER;
    }

    public function getName(): string
    {
        return 'Voucher';
    }
}
