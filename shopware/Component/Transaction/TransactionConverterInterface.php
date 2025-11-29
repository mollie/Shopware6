<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct as ShopwarePaymentTransactionStruct;
use Shopware\Core\Framework\Context;

interface TransactionConverterInterface
{
    public function convert(ShopwarePaymentTransactionStruct $transactionStruct, Context $context): PaymentTransactionStruct;
}
