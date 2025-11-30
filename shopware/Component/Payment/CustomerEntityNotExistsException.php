<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

final class CustomerEntityNotExistsException extends \Exception
{
    public function __construct(string $customerId,string $transactionId)
    {
        $message = sprintf('OrderCustomerEntity with id %s was loaded without CustomerEntity for transactionId: %s', $customerId,$transactionId);
        parent::__construct($message);
    }
}
