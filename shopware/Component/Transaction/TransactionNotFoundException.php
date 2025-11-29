<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

final class TransactionNotFoundException extends \Exception
{
    public function __construct(string $transactionId)
    {
        parent::__construct(sprintf('Transaction with ID %s not found', $transactionId));
    }
}
