<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Mollie\Shopware\Mollie;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class CartItemShopwareStruct extends Struct
{
    use JsonSerializableTrait;

    public const SHIPPING = 'SHIPPING';

    public function __construct(
        private string $id,
        private string $label,
        private float $unitPrice,
        private int $quantity,
        private float $totalPrice,
        private float $discountedPrice,
        private string $productNumber,
        private bool $isPromotion,
        private bool $isDelivery,
        private CartItemTaxStruct $tax,
        private CartItemPromotionStruct $promotion = new CartItemPromotionStruct(),
    ) {
        $this->unitPrice = round($unitPrice, Mollie::ROUNDING_PRECISION);
        $this->totalPrice = round($totalPrice, Mollie::ROUNDING_PRECISION);
        $this->discountedPrice = round($discountedPrice, Mollie::ROUNDING_PRECISION);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getDiscountedPrice(): float
    {
        return $this->discountedPrice;
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getPromotion(): CartItemPromotionStruct
    {
        return $this->promotion;
    }

    public function isPromotion(): bool
    {
        return $this->isPromotion;
    }

    public function isDelivery(): bool
    {
        return $this->isDelivery;
    }

    public function getTax(): CartItemTaxStruct
    {
        return $this->tax;
    }
}
