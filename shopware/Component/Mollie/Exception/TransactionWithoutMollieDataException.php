<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class TransactionWithoutMollieDataException extends \Exception
{
    public function __construct(string $transactionId)
    {
        $message = sprintf('Transaction with ID %s is without mollie payments information', $transactionId);
        parent::__construct($message);
    }
}
