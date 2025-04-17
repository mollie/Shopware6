<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct as ShopwarePaymentTransactionStruct;
use Shopware\Core\Framework\Context;

interface TransactionConverterInterface
{
    /**
     * @param AsyncPaymentTransactionStruct|ShopwarePaymentTransactionStruct $transactionStruct
     */
    public function convert($transactionStruct, Context $context): PaymentTransactionStruct;
}
