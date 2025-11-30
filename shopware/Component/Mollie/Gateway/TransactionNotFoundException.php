<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

final class TransactionNotFoundException extends \Exception
{
    public function __construct(string $transactionId)
    {
        $message = sprintf('Transaction with ID %s not found.', $transactionId);
        parent::__construct($message);
    }
}
