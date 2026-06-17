<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Mollie\Shopware\Mollie;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class CartItemPromotionStruct extends Struct
{
    use JsonSerializableTrait;

    public function __construct(
        private float $discount = 0.0,
        private int $quantity = 0,
        private float $taxValue = 0.0,
    ) {
        $this->discount = round($discount, Mollie::ROUNDING_PRECISION);
        $this->taxValue = round($taxValue, Mollie::ROUNDING_PRECISION);
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getTaxValue(): float
    {
        return $this->taxValue;
    }
}
