<?php
declare(strict_types=1);

namespace Mollie\Shopware\Exception;

final class TransactionWithoutOrderException extends \Exception
{
    public function __construct(string $transactionId)
    {
        $message = sprintf('Transaction with ID %s was loaded without OrderEntity.', $transactionId);
        parent::__construct($message);
    }
}
