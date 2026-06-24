<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<LineItem>
 */
final class LineItemCollection extends Collection
{
    public function getTotal(): float
    {
        $total = 0.0;
        foreach ($this->getElements() as $item) {
            $total += $item->getAmount()->getValue();
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    public function filterByOrderLineItems(OrderLineItemCollection $shopwareLineItems): static
    {
        return $this->filter(function (LineItem $mollieLine) use ($shopwareLineItems): bool {
            $shopwareLineItemId = $mollieLine->getShopwareLineItemId();
            if ($shopwareLineItemId === '') {
                return false;
            }

            return $shopwareLineItems->has($shopwareLineItemId);
        });
    }

    public function filterByDeliveries(OrderDeliveryCollection $deliveries): static
    {
        return $this->filter(function (LineItem $mollieLine) use ($deliveries): bool {
            $shopwareLineItemId = $mollieLine->getShopwareLineItemId();
            if ($shopwareLineItemId === '') {
                return false;
            }

            return $deliveries->has($shopwareLineItemId);
        });
    }

    public function findByShopwareId(string $shopwareId): ?LineItem
    {
        foreach ($this->getElements() as $item) {
            if ($item->getShopwareLineItemId() === $shopwareId) {
                return $item;
            }
        }

        return null;
    }
}
