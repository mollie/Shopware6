<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

final class RefundableTotalCalculator
{
    /**
     * Computes the original refundable total of the order: the sum of the non-credit line items
     * plus shipping, always as gross amount.
     *
     * Credit line items (added by credit notes) and delivery discount placeholders are excluded,
     * so the total stays stable across refunds even though the credit notes recalculate
     * order->getAmountTotal() downwards. For net orders the line taxes are added on top, because
     * the Mollie payment was created with gross amounts and the refund may include the tax.
     */
    public function calculate(OrderEntity $order): float
    {
        $isNet = (string) $order->getTaxStatus() === CartPrice::TAX_STATE_NET;
        $total = 0.0;

        foreach ($order->getLineItems() ?? new OrderLineItemCollection() as $lineItem) {
            if ($lineItem->getType() === ShopwareLineItem::CREDIT_LINE_ITEM_TYPE) {
                continue;
            }

            if (LineItem::isDeliveryDiscountPlaceholder($lineItem)) {
                continue;
            }

            $total += $this->toGross($lineItem->getTotalPrice(), $lineItem->getPrice(), $isNet);
        }

        foreach ($order->getDeliveries() ?? new OrderDeliveryCollection() as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $total += $this->toGross($shippingCosts->getTotalPrice(), $shippingCosts, $isNet);
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    private function toGross(float $totalPrice, ?CalculatedPrice $price, bool $isNet): float
    {
        if (! $isNet || ! $price instanceof CalculatedPrice) {
            return $totalPrice;
        }

        return $totalPrice + $price->getCalculatedTaxes()->getAmount();
    }
}
