<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

final class ApiException extends \Exception
{
    public function __construct(int $statusCode, string $title, string $details, string $field)
    {
        $message = sprintf('Error in field %s. %s: %s ', $field, $title, $details);
        parent::__construct($message, $statusCode);
    }
}
