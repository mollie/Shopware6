<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotFetchMollieCustomerMandatesException extends \Exception
{
    public function __construct(
        string $mollieCustomerId,
        string $salesChannelId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Could not fetch the mandates of the Mollie customer ID %s for sales channel %s",
            $mollieCustomerId,
            $salesChannelId
        );
        parent::__construct($message, 0, $previous);
    }
}
