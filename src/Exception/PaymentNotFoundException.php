<?php

namespace Kiener\MolliePayments\Exception;

class PaymentNotFoundException extends \RuntimeException
{
    public function __construct(string $mollieOrderId)
    {
        $message = sprintf('A payment for the Mollie order %s could not be found', $mollieOrderId);
        parent::__construct($message);
    }
}
