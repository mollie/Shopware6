<?php

namespace Mollie\Shopware\Component\Mollie\Exception;

final class MissingShippingMethodException extends \InvalidArgumentException
{
    public function __construct()
    {
        $message = 'Shipping method does not exist';
        parent::__construct($message);
    }
}