<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;

final class PayByBankPayment extends AbstractMolliePaymentHandler implements BankTransferAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAY_BY_BANK;
    }

    public function getName(): string
    {
        return 'Pay by Bank';
    }

    public function getDescription(): string
    {
        return 'Pay by Bank is a convenient and user-friendly way for your customers to directly pay from their bank accounts. The Pay By Bank payment method offers a secure, cost-effective, and efficient alternative to traditional card payments, facilitating instant or near-instant transactions. With lower transaction costs than card payments and BNPL methods, you keep more of what you earn.';
    }
}
