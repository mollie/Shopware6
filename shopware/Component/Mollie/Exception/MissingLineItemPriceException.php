<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Exception;

final class MissingLineItemPriceException extends \InvalidArgumentException
{
    public function __construct(string $label)
    {
        $message = sprintf('Order line item "%s" is missing price', $label);
        parent::__construct($message);
    }
}
