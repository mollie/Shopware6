<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class ShippingItemCollection implements \JsonSerializable
{
    /** @var list<ShippingItem> */
    private array $items = [];

    public function add(ShippingItem $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<ShippingItem> */
    public function all(): array
    {
        return $this->items;
    }

    public function getTotalAmount(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getAmount();
        }

        return $total;
    }

    public function getDescription(): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = $item->getDescription();
        }

        return implode(', ', $parts);
    }

    /** @return list<ShippingItem> */
    public function jsonSerialize(): array
    {
        return array_values(array_filter(
            $this->items,
            function (ShippingItem $item) {
                return $item->getMollieLineId() !== null;
            }
        ));
    }
}
