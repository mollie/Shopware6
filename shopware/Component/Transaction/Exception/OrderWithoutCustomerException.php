<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction\Exception;

final class OrderWithoutCustomerException extends TransactionException
{
    public function __construct(string $orderId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Order %s does not have a customer in Shopware',$orderId);
        parent::__construct($message, $code, $previous);
    }
}
