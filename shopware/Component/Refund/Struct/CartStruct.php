<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Struct\Struct;

final class CartStruct extends Struct
{
    /** @var CartItemStruct[] */
    private array $items = [];

    public static function fromOrder(OrderEntity $order): self
    {
        $cart = new self();
        $promotionCompositions = $cart->extractPromotionCompositions($order);

        $lineItems = $order->getLineItems();
        if ($lineItems !== null) {
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
                    continue;
                }

                if ($lineItem->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE) {
                    if ($lineItem->getTotalPrice() !== 0.0) {
                        $cart->addItem($cart->buildPromotionItem($lineItem));
                    }
                    continue;
                }

                $cart->addItem($cart->buildProductItem($lineItem, $promotionCompositions));
            }
        }

        $deliveries = $order->getDeliveries();

        if ($deliveries === null) {
            return $cart;
        }

        foreach ($deliveries as $delivery) {
            if ($delivery->getShippingCosts()->getTotalPrice() < 0.0) {
                $cart->addItem($cart->buildDeliveryPromotionItem($delivery));
                continue;
            }

            $cart->addItem($cart->buildDeliveryItem($delivery));
        }

        return $cart;
    }

    /**
     * @return CartItemStruct[]
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    private function addItem(CartItemStruct $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @param array<mixed> $promotionCompositions
     */
    private function buildProductItem(OrderLineItemEntity $lineItem, array $promotionCompositions): CartItemStruct
    {
        $promotionDiscount = 0.0;
        $promotionAffectedQty = 0;
        $promotionTaxValue = 0.0;

        foreach ($promotionCompositions as $composition) {
            foreach ($composition as $compItem) {
                if (($compItem['id'] ?? '') === $lineItem->getReferencedId()) {
                    $promotionDiscount += round((float) ($compItem['discount'] ?? 0), Mollie::ROUNDING_PRECISION);
                    $promotionAffectedQty += (int) ($compItem['quantity'] ?? 0);
                    $promotionTaxValue += round((float) ($compItem['taxValue'] ?? 0), Mollie::ROUNDING_PRECISION);
                }
            }
        }

        $totalPrice = $lineItem->getTotalPrice();

        return new CartItemStruct(
            new CartItemShopwareStruct(
                $lineItem->getId(),
                (string) $lineItem->getLabel(),
                $lineItem->getUnitPrice(),
                $lineItem->getQuantity(),
                $totalPrice,
                $totalPrice - $promotionDiscount,
                (string) ($lineItem->getPayload()['productNumber'] ?? ''),
                false,
                false,
                $this->calculateTaxBreakdown($lineItem->getPrice(), $lineItem->getQuantity()),
                new CartItemPromotionStruct($promotionDiscount, $promotionAffectedQty, $promotionTaxValue),
            )
        );
    }

    private function buildPromotionItem(OrderLineItemEntity $lineItem): CartItemStruct
    {
        $totalPrice = $lineItem->getTotalPrice();

        return new CartItemStruct(
            new CartItemShopwareStruct(
                $lineItem->getId(),
                (string) $lineItem->getLabel(),
                $lineItem->getUnitPrice(),
                $lineItem->getQuantity(),
                $totalPrice,
                $totalPrice,
                (string) $lineItem->getReferencedId(),
                true,
                false,
                $this->calculateTaxBreakdown($lineItem->getPrice(), $lineItem->getQuantity()),
            )
        );
    }

    private function buildDeliveryItem(OrderDeliveryEntity $delivery): CartItemStruct
    {
        $costs = $delivery->getShippingCosts();
        $method = $delivery->getShippingMethod();
        $label = $method instanceof ShippingMethodEntity ? (string) $method->getName() : 'UNKNOWN NAME';

        return new CartItemStruct(
            new CartItemShopwareStruct(
                $delivery->getId(),
                $label,
                $costs->getUnitPrice(),
                $costs->getQuantity(),
                $costs->getTotalPrice(),
                $costs->getTotalPrice(),
                CartItemShopwareStruct::SHIPPING,
                false,
                true,
                $this->calculateTaxBreakdown($costs, $costs->getQuantity()),
            )
        );
    }

    private function buildDeliveryPromotionItem(OrderDeliveryEntity $delivery): CartItemStruct
    {
        $costs = $delivery->getShippingCosts();
        $method = $delivery->getShippingMethod();
        $label = $method instanceof ShippingMethodEntity ? (string) $method->getName() : CartItemShopwareStruct::SHIPPING;

        return new CartItemStruct(
            new CartItemShopwareStruct(
                $delivery->getId(),
                $label,
                $costs->getTotalPrice(),
                $costs->getQuantity(),
                $costs->getTotalPrice(),
                $costs->getTotalPrice(),
                CartItemShopwareStruct::SHIPPING,
                true,
                false,
                $this->calculateTaxBreakdown($costs, $costs->getQuantity()),
            )
        );
    }

    /**
     * @return array<mixed>
     */
    private function extractPromotionCompositions(OrderEntity $order): array
    {
        $compositions = [];
        $lineItems = $order->getLineItems();

        if ($lineItems === null) {
            return $compositions;
        }

        foreach ($lineItems as $lineItem) {
            $payload = $lineItem->getPayload() ?? [];
            if (isset($payload['composition']) && is_array($payload['composition'])) {
                $compositions[] = $payload['composition'];
            }
        }

        return $compositions;
    }

    private function calculateTaxBreakdown(?CalculatedPrice $price, int $quantity): CartItemTaxStruct
    {
        if ($price === null || $quantity === 0) {
            return new CartItemTaxStruct(0.0, 0.0, 0.0);
        }

        $taxTotal = round($price->getCalculatedTaxes()->getAmount(), Mollie::ROUNDING_PRECISION);
        $taxPerItem = floor($taxTotal / $quantity * 100) / 100;
        $taxDiff = round($taxTotal - ($taxPerItem * $quantity), Mollie::ROUNDING_PRECISION);

        return new CartItemTaxStruct($taxTotal, $taxPerItem, $taxDiff);
    }
}
