<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\PaymentLinkAwareInterface;

final class MyBankPayment extends AbstractMolliePaymentHandler implements BankTransferAwareInterface, PaymentLinkAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::MY_BANK;
    }

    public function getName(): string
    {
        return 'MyBank';
    }
}
