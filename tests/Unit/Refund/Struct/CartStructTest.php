<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Struct;

use Mollie\Shopware\Component\Refund\Struct\CartItemShopwareStruct;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;

#[CoversClass(CartStruct::class)]
final class CartStructTest extends TestCase
{
    public function testFromOrderWithNoLineItemsAndNoDeliveries(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection());
        $order->setDeliveries(new OrderDeliveryCollection());

        $cart = CartStruct::fromOrder($order);

        $this->assertCount(0, $cart->jsonSerialize());
    }

    public function testFromOrderIncludesProductLineItem(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildProductLineItem('line-1', 'SW-1000', 'T-Shirt', 19.99, 2),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(1, $items);
        $shopware = $items[0]->getShopware();
        $this->assertSame('line-1', $shopware->getId());
        $this->assertSame('T-Shirt', $shopware->getLabel());
        $this->assertSame(19.99, $shopware->getUnitPrice());
        $this->assertSame(2, $shopware->getQuantity());
        $this->assertFalse($shopware->isPromotion());
        $this->assertFalse($shopware->isDelivery());
    }

    public function testFromOrderSkipsCreditLineItems(): void
    {
        $credit = new OrderLineItemEntity();
        $credit->setId('credit-1');
        $credit->setType(LineItem::CREDIT_LINE_ITEM_TYPE);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$credit]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(0, $items);
    }

    public function testFromOrderIncludesNonZeroPromotionLineItem(): void
    {
        $promo = $this->buildPromotionLineItem('promo-1', 'SALE10', -5.0);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$promo]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(1, $items);
        $this->assertTrue($items[0]->getShopware()->isPromotion());
    }

    public function testFromOrderSkipsZeroPricePromotionLineItem(): void
    {
        $promo = $this->buildPromotionLineItem('promo-zero', 'FREE', 0.0);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$promo]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(0, $items);
    }

    public function testFromOrderIncludesDelivery(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection());
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildDelivery('delivery-1', 'DHL', 4.99),
        ]));

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(1, $items);
        $shopware = $items[0]->getShopware();
        $this->assertSame('delivery-1', $shopware->getId());
        $this->assertSame('DHL', $shopware->getLabel());
        $this->assertSame(CartItemShopwareStruct::SHIPPING, $shopware->getProductNumber());
        $this->assertFalse($shopware->isPromotion());
        $this->assertTrue($shopware->isDelivery());
    }

    public function testFromOrderTreatsNegativeShippingCostAsDeliveryPromotion(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection());
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildDelivery('delivery-promo', 'DHL', -4.99),
        ]));

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(1, $items);
        $shopware = $items[0]->getShopware();
        $this->assertTrue($shopware->isPromotion());
        $this->assertFalse($shopware->isDelivery());
    }

    public function testFromOrderLabelsShippingDiscountWithVoucherName(): void
    {
        $placeholder = $this->buildPromotionLineItem('promo-line', 'mollie_free_shipping', 0.0);
        $placeholder->setLabel('Mollie test: free shipping');
        $placeholder->setPayload(['discountScope' => 'delivery']);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$placeholder]));
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildDelivery('delivery-promo', 'DHL', -4.99),
        ]));

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $this->assertCount(1, $items);
        $shopware = $items[0]->getShopware();
        $this->assertTrue($shopware->isPromotion());
        $this->assertSame('Mollie test: free shipping', $shopware->getLabel());
    }

    public function testFromOrderCalculatesTaxBreakdown(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildProductLineItem('line-1', 'SW-1', 'Item', 10.0, 2, taxRate: 19.0),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $tax = $items[0]->getShopware()->getTax();
        $this->assertGreaterThan(0.0, $tax->getTotalItemTax());
    }

    public function testFromOrderAppliesPromotionCompositionToProduct(): void
    {
        $product = $this->buildProductLineItem('prod-1', 'SW-REF', 'Product', 10.0, 1);

        $promo = $this->buildPromotionLineItem('promo-1', 'SW-REF', -2.0, composition: [
            ['id' => 'SW-REF', 'discount' => 2.0, 'quantity' => 1, 'taxValue' => 0.32],
        ]);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$product, $promo]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $items = CartStruct::fromOrder($order)->jsonSerialize();

        $productItem = $items[0]->getShopware();
        $this->assertSame(2.0, $productItem->getPromotion()->getDiscount());
        $this->assertSame(8.0, $productItem->getDiscountedPrice());
    }

    public function testApplyRefundedQuantitiesSetsMatchingItems(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildProductLineItem('line-1', 'SW-1', 'A', 10.0, 5),
            $this->buildProductLineItem('line-2', 'SW-2', 'B', 10.0, 5),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $cart = CartStruct::fromOrder($order);
        $cart->applyRefundedQuantities(['line-1' => 2]);

        $items = $cart->jsonSerialize();
        $this->assertSame(2, $items[0]->getRefunded());
        $this->assertSame(0, $items[1]->getRefunded());
    }

    public function testJsonSerializeReturnsItemsArray(): void
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildProductLineItem('a', 'SW-A', 'A', 5.0, 1),
            $this->buildProductLineItem('b', 'SW-B', 'B', 5.0, 1),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection());

        $cart = CartStruct::fromOrder($order);

        $this->assertIsArray($cart->jsonSerialize());
        $this->assertCount(2, $cart->jsonSerialize());
    }

    private function buildProductLineItem(
        string $id,
        string $productNumber,
        string $label,
        float $unitPrice,
        int $quantity,
        float $taxRate = 19.0,
    ): OrderLineItemEntity {
        $totalPrice = $unitPrice * $quantity;
        $taxAmount = round($totalPrice * ($taxRate / 100), 2);

        $price = new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            new CalculatedTaxCollection([new CalculatedTax($taxAmount, $taxRate, $totalPrice)]),
            new TaxRuleCollection(),
            $quantity,
        );

        $item = new OrderLineItemEntity();
        $item->setId($id);
        $item->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $item->setLabel($label);
        $item->setUnitPrice($unitPrice);
        $item->setQuantity($quantity);
        $item->setTotalPrice($totalPrice);
        $item->setReferencedId($productNumber);
        $item->setPayload(['productNumber' => $productNumber]);
        $item->setPrice($price);

        return $item;
    }

    private function buildPromotionLineItem(
        string $id,
        string $referencedId,
        float $totalPrice,
        array $composition = [],
    ): OrderLineItemEntity {
        $price = new CalculatedPrice(
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        );

        $item = new OrderLineItemEntity();
        $item->setId($id);
        $item->setType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $item->setLabel('Promotion');
        $item->setUnitPrice($totalPrice);
        $item->setQuantity(1);
        $item->setTotalPrice($totalPrice);
        $item->setReferencedId($referencedId);
        $item->setPayload($composition !== [] ? ['composition' => $composition] : []);
        $item->setPrice($price);

        return $item;
    }

    private function buildDelivery(string $id, string $methodName, float $shippingCost): OrderDeliveryEntity
    {
        $price = new CalculatedPrice(
            $shippingCost,
            $shippingCost,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        );

        $method = new ShippingMethodEntity();
        $method->setId($id . '-method');
        $method->setName($methodName);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id);
        $delivery->setShippingCosts($price);
        $delivery->setShippingMethod($method);

        return $delivery;
    }
}
