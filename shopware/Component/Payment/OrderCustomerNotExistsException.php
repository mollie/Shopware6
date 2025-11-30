<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

final class OrderCustomerNotExistsException extends \Exception
{
    public function __construct(string $transactionId)
    {
        $message = sprintf('Order customer entity was not loaded with transactionId: %s', $transactionId);
        parent::__construct($message);
    }
}
