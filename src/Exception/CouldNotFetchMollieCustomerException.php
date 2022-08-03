<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotFetchMollieCustomerException extends \Exception
{
    public function __construct(
        string $mollieCustomerId,
        string $salesChannelId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Could not fetch the Mollie customer ID %s for sales channel %s",
            $mollieCustomerId,
            $salesChannelId
        );
        parent::__construct($message, 0, $previous);
    }
}
