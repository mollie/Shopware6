<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class MissingCountryException extends \InvalidArgumentException
{
    public function __construct()
    {
        $message = 'Country cannot be null';
        parent::__construct($message);
    }
}