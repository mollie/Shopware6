<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class MissingSalutationException extends \InvalidArgumentException
{
    public function __construct()
    {
        $message = 'Salutation cannot be null';
        parent::__construct($message);
    }
}
