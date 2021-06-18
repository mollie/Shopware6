<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotFetchMollieOrderException extends \RuntimeException
{
    public function __construct(string $mollieOrderId)
    {
        $message = sprintf("Could not fetch the Mollie Order for ID %s", $mollieOrderId);
        parent::__construct($message);
    }
}
