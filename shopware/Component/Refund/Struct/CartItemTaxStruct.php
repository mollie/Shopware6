<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Mollie\Shopware\Mollie;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class CartItemTaxStruct extends Struct
{
    use JsonSerializableTrait;

    public function __construct(
        private float $totalItemTax,
        private float $perItemTax,
        private float $totalToPerItemRoundingDiff,
    ) {
        $this->totalItemTax = round($totalItemTax, Mollie::ROUNDING_PRECISION);
        $this->perItemTax = round($perItemTax, Mollie::ROUNDING_PRECISION);
        $this->totalToPerItemRoundingDiff = round($totalToPerItemRoundingDiff, Mollie::ROUNDING_PRECISION);
    }

    public function getTotalItemTax(): float
    {
        return $this->totalItemTax;
    }

    public function getPerItemTax(): float
    {
        return $this->perItemTax;
    }

    public function getTotalToPerItemRoundingDiff(): float
    {
        return $this->totalToPerItemRoundingDiff;
    }
}
