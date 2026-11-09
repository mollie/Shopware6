<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\PaymentLinkAwareInterface;

final class MultiBancoPayment extends AbstractMolliePaymentHandler implements BankTransferAwareInterface, PaymentLinkAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::MULTI_BANCO;
    }

    public function getName(): string
    {
        return 'Multibanco';
    }
}
