<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\PaymentLink;

use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;

interface PaymentLinkBuilderInterface
{
    public function build(TransactionDataStruct $transactionData): CreatePaymentLink;
}
