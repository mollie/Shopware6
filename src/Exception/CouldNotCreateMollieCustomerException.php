<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotCreateMollieCustomerException extends \Exception
{
    public function __construct(
        string $customerNumber,
        string $customerName,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Could not create a customer at Mollie for customer %s (%s)",
            $customerNumber,
            $customerName
        );
        parent::__construct($message, 0, $previous);
    }
}
