<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotRevokeMollieCustomerMandateException extends \Exception
{
    public function __construct(
        string $mandateId,
        string $mollieCustomerId,
        string $salesChannelId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Error while revoking the mandate ID %s of the Mollie customer ID %s for sales channel %s. The mandate does not exist or is no longer available.",
            $mandateId,
            $mollieCustomerId,
            $salesChannelId
        );

        parent::__construct($message, 0, $previous);
    }
}
