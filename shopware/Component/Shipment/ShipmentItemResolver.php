<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\ShippingItem;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Shipment\Route\ShippingException;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * Pure computation of what a shipment consists of: resolves requested items against the order,
 * builds the line/delivery custom-field upserts and the Mollie ShippingItemCollection, and answers
 * shipment-state questions (fully shipped, prior shipments, cancellations). Holds no dependencies.
 */
final class ShipmentItemResolver
{
    /**
     * @return list<array{id: string, quantity: int}>
     */
    public function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($item['id'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * Builds the list of all items of an order that are not yet fully shipped or cancelled.
     *
     * @return list<array{id: string, quantity: int}>
     */
    public function buildRemainingItems(OrderEntity $order): array
    {
        $items = [];
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shipped = (int) ($fields['quantity'] ?? 0);
            $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);
            $remaining = $lineItem->getQuantity() - $shipped - $cancelled;

            if ($remaining > 0) {
                $items[] = [
                    'id' => $lineItem->getId(),
                    'quantity' => $remaining,
                ];
            }
        }

        return $items;
    }

    /**
     * @param list<array{id: string, quantity: int}> $items
     *
     * @return list<array{id: string, customFields: array<string, mixed>}>
     */
    public function collectLineItemUpserts(array $items, OrderLineItemCollection $lineItems, string $orderId, ShippingItemCollection $shippingItems, CurrencyEntity $currency, string $taxStatus): array
    {
        $lineUpserts = [];

        foreach ($items as $item) {
            $rawId = (string) $item['id'];
            $requestedQuantity = (int) $item['quantity'];

            $lineItem = $this->findLineItem($lineItems, $rawId);

            if (! $lineItem instanceof OrderLineItemEntity) {
                throw ShippingException::lineItemNotFound(strtolower($rawId), $orderId);
            }

            $oldState = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? ['quantity' => 0];
            $shippedQuantity = (int) ($oldState['quantity'] ?? 0);

            if ($lineItem->getQuantity() === $shippedQuantity) {
                throw ShippingException::lineItemAlreadyShipped($lineItem->getId(), $orderId);
            }

            $newQuantity = $shippedQuantity + $requestedQuantity;

            if ($newQuantity > $lineItem->getQuantity()) {
                throw ShippingException::shippingQuantityTooHigh($lineItem->getId(), $orderId, $newQuantity, $lineItem->getQuantity());
            }

            $product = $lineItem->getProduct();
            $name = $product !== null ? (string) $product->getName() : (string) $lineItem->getLabel();
            $mollieLineId = ($lineItem->getCustomFields()[Mollie::EXTENSION] ?? [])['order_line_id'] ?? null;

            // Reuse LineItem's net->gross normalization so the capture amount matches the amount sent
            // at payment creation; getUnitPrice() alone is net for net-tax orders.
            $grossLine = LineItem::fromOrderLine($lineItem, $currency, $taxStatus);
            $shippingItem = new ShippingItem(
                $requestedQuantity,
                $requestedQuantity . 'x ' . $name,
                $grossLine->getUnitPrice()->getValue() * $requestedQuantity,
                $mollieLineId !== null ? (string) $mollieLineId : null,
            );
            $shippingItems->add($shippingItem);

            $lineUpserts[] = [
                'id' => $lineItem->getId(),
                'customFields' => [
                    Mollie::EXTENSION => array_merge($oldState, ['quantity' => $newQuantity]),
                ],
            ];
        }

        return $lineUpserts;
    }

    /**
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     *
     * @return list<array{id: string, customFields: array<string, mixed>}>
     */
    public function collectDeliveryUpserts(array $lineUpserts, ShippingItemCollection $shippingItems, OrderDeliveryCollection $deliveryCollection, CurrencyEntity $currency, string $taxStatus): array
    {
        $deliveryUpserts = [];
        $targetLineItemIds = array_column($lineUpserts, 'id');

        foreach ($deliveryCollection as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $shippingCostsQuantity = $shippingCosts->getQuantity();
            $positions = $delivery->getPositions();
            if ($positions === null) {
                continue;
            }

            $oldState = $delivery->getCustomFields()[Mollie::EXTENSION] ?? ['quantity' => 0];
            if ($shippingCostsQuantity === (int) ($oldState['quantity'] ?? 0)) {
                continue;
            }

            $deliveryBelongsToItems = false;

            // A delivery belongs to our shipment if at least one of its positions references one of the resolved line item IDs
            foreach ($positions as $position) {
                if (in_array($position->getOrderLineItemId(), $targetLineItemIds, true)) {
                    $deliveryBelongsToItems = true;
                    break;
                }
            }

            if ($deliveryBelongsToItems === false) {
                continue;
            }

            $shippingMethod = $delivery->getShippingMethod();
            if ($shippingMethod === null) {
                continue;
            }

            $mollieLineId = ($delivery->getCustomFields()[Mollie::EXTENSION] ?? [])['order_line_id'] ?? null;

            // Reuse LineItem's net->gross normalization so shipping costs are captured gross for
            // net-tax orders, consistent with the payment payload.
            $grossDelivery = LineItem::fromDelivery($delivery, $currency, $taxStatus);
            $shippingItem = new ShippingItem(
                $shippingCostsQuantity,
                $shippingCostsQuantity . 'x ' . $shippingMethod->getName(),
                $grossDelivery->getUnitPrice()->getValue() * $shippingCostsQuantity,
                $mollieLineId !== null ? (string) $mollieLineId : null,
            );
            $shippingItems->add($shippingItem);

            $deliveryUpserts[] = [
                'id' => $delivery->getId(),
                'customFields' => [
                    Mollie::EXTENSION => ['quantity' => $shippingCostsQuantity],
                ],
            ];
        }

        return $deliveryUpserts;
    }

    /**
     * Sums the gross amount of everything that has already been shipped (line items and shipping
     * costs), using LineItem's net->gross normalization so it matches the amount that should have
     * been captured.
     */
    public function sumShippedGross(OrderLineItemCollection $lineItems, OrderDeliveryCollection $deliveryCollection, CurrencyEntity $currency, string $taxStatus): float
    {
        $total = 0.0;

        foreach ($lineItems as $lineItem) {
            $shippedQuantity = (int) (($lineItem->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0);
            if ($shippedQuantity <= 0) {
                continue;
            }
            $grossLine = LineItem::fromOrderLine($lineItem, $currency, $taxStatus);
            $total += $grossLine->getUnitPrice()->getValue() * $shippedQuantity;
        }

        foreach ($deliveryCollection as $delivery) {
            $shippedQuantity = (int) (($delivery->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0);
            if ($shippedQuantity <= 0 || $delivery->getShippingMethod() === null) {
                continue;
            }
            $grossDelivery = LineItem::fromDelivery($delivery, $currency, $taxStatus);
            $total += $grossDelivery->getUnitPrice()->getValue() * $shippedQuantity;
        }

        return $total;
    }

    /**
     * Whether any line item has already been shipped in an earlier shipment. Used to capture
     * the rounding difference only once, on the first shipment.
     */
    public function hasPriorShipments(OrderLineItemCollection $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            if ((int) ($fields['quantity'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    public function hasCancelledItems(OrderLineItemCollection $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            if ((int) ($fields['cancelled_quantity'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether all order line items are fully handled (shipped or cancelled) after the current batch.
     * Items in $lineUpserts carry the updated shipped quantity; all others are read from custom fields.
     *
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     */
    public function isFullyShipped(OrderLineItemCollection $lineItems, array $lineUpserts): bool
    {
        $upsertQuantities = [];
        foreach ($lineUpserts as $upsert) {
            $upsertQuantities[$upsert['id']] = (int) ($upsert['customFields'][Mollie::EXTENSION]['quantity'] ?? 0);
        }

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getQuantity() <= 0) {
                continue;
            }

            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shipped = $upsertQuantities[$lineItem->getId()] ?? (int) ($fields['quantity'] ?? 0);
            $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);

            if (($shipped + $cancelled) < $lineItem->getQuantity()) {
                return false;
            }
        }

        return true;
    }

    private function findLineItem(OrderLineItemCollection $lineItems, string $idOrProductNumber): ?OrderLineItemEntity
    {
        $direct = $lineItems->get(strtolower($idOrProductNumber));
        if ($direct instanceof OrderLineItemEntity) {
            return $direct;
        }

        return $lineItems->firstWhere(function (OrderLineItemEntity $product) use ($idOrProductNumber) {
            return $product->getProduct()?->getProductNumber() === $idOrProductNumber;
        });
    }
}
