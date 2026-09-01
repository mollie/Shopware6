<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;

final class FakeBankTransferPaymentHandler extends AbstractMolliePaymentHandler implements BankTransferAwareInterface
{
    public function __construct()
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BANK_TRANSFER;
    }

    public function getName(): string
    {
        return 'Fake bank transfer payment method';
    }
}
