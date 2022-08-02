<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotExtractMollieOrderLineIdException extends \RuntimeException
{
    public function __construct(
        string $lineItemId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf('Could not extract the Mollie Order Line ID for line item with id %s', $lineItemId);
        parent::__construct($message, 0, $previous);
    }
}
