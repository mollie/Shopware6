<?php

namespace Kiener\MolliePayments\Exception;

class CouldNotCreateMollieRefundException extends \RuntimeException
{
    public function __construct(string $mollieOrderId, string $orderNumber)
    {
        $message = sprintf("Could not create a refund for order %s (Order number %s)",
            $mollieOrderId,
            $orderNumber
        );

        parent::__construct($message);
    }
}
