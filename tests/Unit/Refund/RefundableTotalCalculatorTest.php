<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Refund\RefundableTotalCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(RefundableTotalCalculator::class)]
final class RefundableTotalCalculatorTest extends TestCase
{
    public function testGrossOrderUsesTheLineTotals(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildLineItem('line-1', 29.87, 2, 19.0),
            $this->buildLineItem('line-2', 19.90, 1, 7.0),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildDelivery('delivery-1', 4.85, 15.75),
        ]));

        $calculator = new RefundableTotalCalculator();

        $this->assertSame(84.49, $calculator->calculate($order));
    }

    /**
     * Net orders store net line totals, while the Mollie payment was created with the gross
     * amount. Without adding the taxes the cap would be 11.50 EUR below the payment amount.
     */
    public function testNetOrderAddsTheLineTaxes(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_NET);
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildNetLineItem('line-1', 25.10, 2, 19.0, 9.54),
            $this->buildNetLineItem('line-2', 18.60, 1, 7.0, 1.30),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildNetDelivery('delivery-1', 4.19, 15.75, 0.66),
        ]));

        $calculator = new RefundableTotalCalculator();

        $this->assertSame(84.49, $calculator->calculate($order));
    }

    public function testCreditLineItemsAreIgnored(): void
    {
        $credit = $this->buildLineItem('credit-1', -10.0, 1, 19.0);
        $credit->setType(ShopwareLineItem::CREDIT_LINE_ITEM_TYPE);

        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildLineItem('line-1', 20.0, 1, 19.0),
            $credit,
        ]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $calculator = new RefundableTotalCalculator();

        $this->assertSame(20.0, $calculator->calculate($order));
    }

    public function testDeliveryDiscountPlaceholderIsIgnored(): void
    {
        $placeholder = $this->buildLineItem('promotion-1', -4.99, 1, 19.0);
        $placeholder->setType('promotion');
        $placeholder->setPayload(['discountScope' => 'delivery']);

        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildLineItem('line-1', 20.0, 1, 19.0),
            $placeholder,
        ]));
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildDelivery('delivery-1', 0.0, 19.0),
        ]));

        $calculator = new RefundableTotalCalculator();

        $this->assertSame(20.0, $calculator->calculate($order));
    }

    public function testOrderWithoutLineItemsAndDeliveries(): void
    {
        $order = new OrderEntity();
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);

        $calculator = new RefundableTotalCalculator();

        $this->assertSame(0.0, $calculator->calculate($order));
    }

    private function buildLineItem(string $id, float $unitPrice, int $quantity, float $taxRate): OrderLineItemEntity
    {
        $totalPrice = $unitPrice * $quantity;
        $taxAmount = $totalPrice * $taxRate / (100 + $taxRate);

        return $this->createLineItem($id, $unitPrice, $totalPrice, $quantity, $taxRate, $taxAmount);
    }

    private function buildNetLineItem(string $id, float $unitPrice, int $quantity, float $taxRate, float $taxAmount): OrderLineItemEntity
    {
        return $this->createLineItem($id, $unitPrice, $unitPrice * $quantity, $quantity, $taxRate, $taxAmount);
    }

    private function createLineItem(string $id, float $unitPrice, float $totalPrice, int $quantity, float $taxRate, float $taxAmount): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setLabel('Line ' . $id);
        $lineItem->setQuantity($quantity);
        $lineItem->setUnitPrice($unitPrice);
        $lineItem->setTotalPrice($totalPrice);
        $lineItem->setPrice($this->buildPrice($unitPrice, $totalPrice, $quantity, $taxRate, $taxAmount));

        return $lineItem;
    }

    private function buildDelivery(string $id, float $totalPrice, float $taxRate): OrderDeliveryEntity
    {
        $taxAmount = $totalPrice * $taxRate / (100 + $taxRate);

        return $this->createDelivery($id, $totalPrice, $taxRate, $taxAmount);
    }

    private function buildNetDelivery(string $id, float $totalPrice, float $taxRate, float $taxAmount): OrderDeliveryEntity
    {
        return $this->createDelivery($id, $totalPrice, $taxRate, $taxAmount);
    }

    private function createDelivery(string $id, float $totalPrice, float $taxRate, float $taxAmount): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id);
        $delivery->setShippingCosts($this->buildPrice($totalPrice, $totalPrice, 1, $taxRate, $taxAmount));

        return $delivery;
    }

    private function buildPrice(float $unitPrice, float $totalPrice, int $quantity, float $taxRate, float $taxAmount): CalculatedPrice
    {
        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $totalPrice);

        return new CalculatedPrice($unitPrice, $totalPrice, new CalculatedTaxCollection([$calculatedTax]), new TaxRuleCollection(), $quantity);
    }
}
