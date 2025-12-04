<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class MissingCalculatedTaxException extends \InvalidArgumentException
{
    public function __construct()
    {
        $message = 'Calculated Tax does not exist';
        parent::__construct($message);
    }
}
