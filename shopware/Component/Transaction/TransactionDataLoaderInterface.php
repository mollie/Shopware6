<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Framework\Context;

interface TransactionDataLoaderInterface
{
    public function findById(string $transactionId,Context $context): TransactionDataStruct;
}
