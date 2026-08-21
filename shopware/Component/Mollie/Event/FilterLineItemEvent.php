<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Event;

use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

/**
 * Dispatched per line item before it becomes part of a Mollie payload, for the cart and the order
 * variant alike. A listener that recognises an item of its own plugin calls disallow(), which
 * cannot be undone, so listener order does not matter. Mollie's own are in Component/ItemFilter.
 */
final class FilterLineItemEvent
{
    private bool $allowed = true;

    public function __construct(private readonly CartLineItem|OrderLineItemEntity $item)
    {
    }

    public function getItem(): CartLineItem|OrderLineItemEntity
    {
        return $this->item;
    }

    public function getType(): string
    {
        return (string) $this->item->getType();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->item->getPayload() ?? [];
    }

    public function getTotalPrice(): float
    {
        $price = $this->item->getPrice();
        if ($price instanceof CalculatedPrice === false) {
            return 0.0;
        }

        return $price->getTotalPrice();
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function disallow(): self
    {
        $this->allowed = false;

        return $this;
    }
}
