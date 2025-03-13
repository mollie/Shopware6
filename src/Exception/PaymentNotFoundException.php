<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

class PaymentNotFoundException extends \RuntimeException
{
    public function __construct(
        string $mollieOrderId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf('A payment for the Mollie order %s could not be found', $mollieOrderId);
        parent::__construct($message, 0, $previous);
    }
}
