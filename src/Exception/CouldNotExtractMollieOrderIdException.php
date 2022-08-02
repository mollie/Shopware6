<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotExtractMollieOrderIdException extends \RuntimeException
{
    public function __construct(
        string $orderNumber,
        ?\Throwable $previous = null
    ) {
        $message = sprintf('Could not extract the Mollie Order ID for order with number %s', $orderNumber);
        parent::__construct($message, 0, $previous);
    }
}
