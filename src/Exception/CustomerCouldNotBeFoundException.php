<?php


namespace Kiener\MolliePayments\Exception;

class CustomerCouldNotBeFoundException extends \Exception
{
    public function __construct(
        string $customerId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            "Could not find a customer with id %s",
            $customerId
        );

        parent::__construct($message, 0, $previous);
    }
}
