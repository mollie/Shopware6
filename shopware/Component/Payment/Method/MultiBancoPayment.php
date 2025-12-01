<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;

final class MultiBancoPayment extends AbstractMolliePaymentHandler implements BankTransferAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::MULTI_BANCO;
    }

    public function getName(): string
    {
        return 'Multibanco';
    }

    public function getDescription(): string
    {
        return "Tap into Portugal's thriving ecommerce scene with Multibanco, one of the most trusted payment methods in the country. With over 30% market share and 900 million annual transactions, accepting Multibanco might just be your key to reaching Portuguese consumers.";
    }
}
