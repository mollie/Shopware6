<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotExtractMollieOrderIdException extends \RuntimeException
{
    public function __construct(string $orderNumber)
    {
        $message = sprintf('Could not extract the Mollie Order ID for order with number %s', $orderNumber);
        parent::__construct($message);
    }
}
