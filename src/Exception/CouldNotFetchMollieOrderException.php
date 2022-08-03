<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotFetchMollieOrderException extends \RuntimeException
{
    public function __construct(
        string $mollieOrderId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf("Could not fetch the Mollie Order for ID %s", $mollieOrderId);
        parent::__construct($message, 0, $previous);
    }
}
