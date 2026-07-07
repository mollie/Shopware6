<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;

interface PaymentLinkMethodResolverInterface
{
    /**
     * Returns the mollie method ids that are allowed for the payment link of the given order.
     *
     * @return string[]
     */
    public function resolve(TransactionDataStruct $transactionData, Context $context): array;
}
