<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotFetchMollieRefundsException extends \RuntimeException
{
    public function __construct(
        string $mollieOrderId,
        string $orderNumber,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Could not fetch refunds for the Mollie Order with ID %s (Order number %s)",
            $mollieOrderId,
            $orderNumber
        );
        parent::__construct($message, 0, $previous);
    }
}
