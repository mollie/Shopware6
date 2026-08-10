<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin;

use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\LineItemFilterInterface;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Order\Admin\Response\CancelStatusEntry;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingStatusEntry;
use Mollie\Shopware\Component\Order\Admin\Response\ShippingTotal;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the shipping/cancel view models for the admin order-detail response. For Orders-API orders
 * the numbers come from the Mollie order lines; for Payments-API orders they are derived from the
 * Shopware line items and deliveries (shipped/cancelled quantities tracked in custom fields).
 */
final class OrderAdminStatusBuilder
{
    public function __construct(
        #[Autowire(service: ShipmentItemResolver::class)]
        private readonly ShipmentItemResolver $itemResolver,
        #[Autowire(service: LineItemFilter::class)]
        private readonly LineItemFilterInterface $lineItemFilter,
    ) {
    }

    /**
     * @return array<string, CancelStatusEntry>
     */
    public function buildCancelStatus(string $mollieOrderId, ?Order $mollieOrder, ?OrderLineItemCollection $lineItems, bool $cancelAllowed): array
    {
        if ($mollieOrder === null) {
            if ($lineItems === null) {
                return [];
            }

            $result = [];
            foreach ($lineItems as $lineItem) {
                // Container line items were never part of the Mollie payload, so they can neither be
                // shipped nor cancelled at Mollie (see ShipmentItemResolver).
                if (! $this->lineItemFilter->isItemAllowed($lineItem)) {
                    continue;
                }

                $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
                $shipped = (int) ($fields['quantity'] ?? 0);
                $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);
                $cancelable = $cancelAllowed ? max(0, $lineItem->getQuantity() - $shipped - $cancelled) : 0;
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
                if (! $this->lineItemFilter->isItemAllowed($lineItem)) {
                    continue;
                }

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

    public function buildShippingTotal(?Order $mollieOrder, OrderEntity $order, bool $shippingAllowed): ShippingTotal
    {
        if ($mollieOrder === null) {
            return $this->buildPaymentsApiShippingTotal($order, $shippingAllowed);
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
            $this->formatAmount($totalAmount),
            $totalQuantity,
            $totalShippable,
        );
    }

    /**
     * Payments-API orders have no Mollie order lines, so the totals are derived from the Shopware
     * line items and deliveries. The amount is the gross amount that was captured for the already
     * shipped items.
     */
    private function buildPaymentsApiShippingTotal(OrderEntity $order, bool $shippingAllowed): ShippingTotal
    {
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $deliveries = $order->getDeliveries() ?? new OrderDeliveryCollection();

        $totalQuantity = 0;
        $totalShippable = 0;

        foreach ($lineItems as $lineItem) {
            if (! $this->lineItemFilter->isItemAllowed($lineItem)) {
                continue;
            }

            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shippedQty = (int) ($fields['quantity'] ?? 0);
            $cancelledQty = (int) ($fields['cancelled_quantity'] ?? 0);

            $totalQuantity += $shippedQty;
            $totalShippable += $shippingAllowed ? max(0, $lineItem->getQuantity() - $shippedQty - $cancelledQty) : 0;
        }

        $currency = $order->getCurrency();
        $totalAmount = $currency !== null
            ? $this->itemResolver->sumShippedGross($lineItems, $deliveries, $currency, (string) $order->getTaxStatus())
            : 0.0;

        return new ShippingTotal(
            $this->formatAmount($totalAmount),
            $totalQuantity,
            $totalShippable,
        );
    }

    /**
     * The administration parses this value as a number, so it must not contain a thousands separator.
     */
    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
