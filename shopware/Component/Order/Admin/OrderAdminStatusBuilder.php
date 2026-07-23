<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin;

use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Order\Admin\Response\CancelStatusEntry;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingStatusEntry;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingTotal;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;

/**
 * Builds the shipping/cancel view models for the admin order-detail response. For Orders-API orders
 * the numbers come from the Mollie order lines; for Payments-API orders they are derived from the
 * Shopware line items and deliveries (shipped/cancelled quantities tracked in custom fields). Holds
 * no dependencies.
 */
final class OrderAdminStatusBuilder
{
    /**
     * @return array<string, CancelStatusEntry>
     */
    public function buildCancelStatus(string $mollieOrderId, ?Order $mollieOrder, ?OrderLineItemCollection $lineItems, bool $shippingAllowed): array
    {
        if ($mollieOrder === null) {
            if ($lineItems === null) {
                return [];
            }

            $result = [];
            foreach ($lineItems as $lineItem) {
                $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shipped = (int) ($fields['quantity'] ?? 0);
                $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);
                $cancelable = $shippingAllowed ? max(0, $lineItem->getQuantity() - $shipped - $cancelled) : 0;
                $result[$lineItem->getId()] = new CancelStatusEntry(
                    '',
                    $lineItem->getId(),
                    $cancelable > 0,
                    $cancelable,
                    $cancelled,
                );
            }

            return $result;
        }

        $result = [];
        foreach ($mollieOrder->getLines() as $line) {
            $shopwareLineItemId = $line->getShopwareLineItemId();
            if ($shopwareLineItemId === '') {
                continue;
            }
            $result[$shopwareLineItemId] = new CancelStatusEntry(
                $mollieOrderId,
                $line->getId(),
                $line->getCancelableQuantity() > 0,
                $line->getCancelableQuantity(),
                $line->getQuantityCanceled(),
            );
        }

        return $result;
    }

    /**
     * @return array<string, ShippingStatusEntry>
     */
    public function buildShippingStatus(string $mollieOrderId, ?Order $mollieOrder, ?OrderLineItemCollection $lineItems, bool $shippingAllowed, ?OrderDeliveryCollection $deliveries = null): array
    {
        if ($mollieOrder === null) {
            if ($lineItems === null) {
                return [];
            }

            $result = [];
            foreach ($lineItems as $lineItem) {
                $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shippedQty = (int) ($fields['quantity'] ?? 0);
                $cancelledQty = (int) ($fields['cancelled_quantity'] ?? 0);
                $shippableQty = $shippingAllowed ? max(0, $lineItem->getQuantity() - $shippedQty - $cancelledQty) : 0;
                $result[$lineItem->getId()] = new ShippingStatusEntry(
                    '',
                    '',
                    $shippableQty > 0,
                    $shippableQty,
                    $shippedQty,
                );
            }

            foreach ($deliveries ?? [] as $delivery) {
                $fields = $delivery->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shippedQty = (int) ($fields['quantity'] ?? 0);
                $totalQty = $delivery->getShippingCosts()->getQuantity();
                $shippableQty = $shippingAllowed ? max(0, $totalQty - $shippedQty) : 0;
                $result[$delivery->getId()] = new ShippingStatusEntry(
                    '',
                    '',
                    $shippableQty > 0,
                    $shippableQty,
                    $shippedQty,
                );
            }

            return $result;
        }

        $result = [];
        foreach ($mollieOrder->getLines() as $line) {
            $shopwareLineItemId = $line->getShopwareLineItemId();
            if ($shopwareLineItemId === '') {
                continue;
            }
            $result[$shopwareLineItemId] = new ShippingStatusEntry(
                $mollieOrderId,
                $line->getId(),
                $line->getShippableQuantity() > 0,
                $line->getShippableQuantity(),
                $line->getQuantityShipped(),
            );
        }

        return $result;
    }

    public function buildShippingTotal(?Order $mollieOrder): ShippingTotal
    {
        if ($mollieOrder === null) {
            return new ShippingTotal('0.00', 0, 0);
        }

        $totalAmount = 0.0;
        $totalQuantity = 0;
        $totalShippable = 0;

        foreach ($mollieOrder->getLines() as $line) {
            $amountShipped = $line->getAmountShipped();
            if ($amountShipped !== null) {
                $totalAmount += (float) $amountShipped->getValue();
            }
            $totalQuantity += $line->getQuantityShipped();
            $totalShippable += $line->getShippableQuantity();
        }

        return new ShippingTotal(
            number_format(round($totalAmount, 2), 2),
            $totalQuantity,
            $totalShippable,
        );
    }
}
