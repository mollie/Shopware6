<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;

interface CreatePaymentBuilderInterface
{
    public function build(PaymentTransactionStruct $transaction): CreatePayment;
}
