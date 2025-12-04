<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class MissingOrderAddressException extends \InvalidArgumentException
{
    public function __construct()
    {
        $message = 'Address should not be null';
        parent::__construct($message);
    }
}
