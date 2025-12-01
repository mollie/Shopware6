<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;

final class DirectDebitPayment extends AbstractMolliePaymentHandler implements DeprecatedMethodAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::DIRECT_DEBIT;
    }

    public function getName(): string
    {
        return 'SEPA Direct Debit';
    }

    public function getDescription(): string
    {
        return 'Mollie allows you to quickly and easily collect recurring payments through SEPA Direct Debit. It only takes 10 minutes to start receiving payments through SEPA Direct Debit and there are no hidden fees involved.';
    }
}
