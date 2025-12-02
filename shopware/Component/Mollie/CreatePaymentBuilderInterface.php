<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Transaction\TransactionDataStruct;

interface CreatePaymentBuilderInterface
{
    public function build(TransactionDataStruct $transactionData): CreatePayment;
}
