<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\LineItemType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

#[CoversClass(LineItemType::class)]
final class LineItemTypeTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('physical', LineItemType::PHYSICAL->value);
        $this->assertSame('digital', LineItemType::DIGITAL->value);
        $this->assertSame('shipping_fee', LineItemType::SHIPPING->value);
        $this->assertSame('discount', LineItemType::DISCOUNT->value);
        $this->assertSame('store_credit', LineItemType::CREDIT->value);
        $this->assertSame('gift_card', LineItemType::GIFT_CARD->value);
        $this->assertSame('surcharge', LineItemType::SURCHARGE->value);
        $this->assertSame('customized-products', LineItemType::LINE_ITEM_TYPE_CUSTOM_PRODUCTS->value);
    }

    public function testFromOrderLineItemWithProductType(): void
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $result = LineItemType::fromOderLineItem($orderLineItem);

        $this->assertSame(LineItemType::PHYSICAL, $result);
    }

    public function testFromOrderLineItemWithCustomProductsType(): void
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setType(LineItemType::LINE_ITEM_TYPE_CUSTOM_PRODUCTS->value);

        $result = LineItemType::fromOderLineItem($orderLineItem);

        $this->assertSame(LineItemType::PHYSICAL, $result);
    }

    public function testFromOrderLineItemWithCreditType(): void
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setType(LineItem::CREDIT_LINE_ITEM_TYPE);

        $result = LineItemType::fromOderLineItem($orderLineItem);

        $this->assertSame(LineItemType::CREDIT, $result);
    }

    public function testFromOrderLineItemWithPromotionType(): void
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setType(LineItem::PROMOTION_LINE_ITEM_TYPE);

        $result = LineItemType::fromOderLineItem($orderLineItem);

        $this->assertSame(LineItemType::DISCOUNT, $result);
    }

    public function testFromOrderLineItemWithUnknownTypeDefaultsToDigital(): void
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setType('unknown_type');

        $result = LineItemType::fromOderLineItem($orderLineItem);

        $this->assertSame(LineItemType::DIGITAL, $result);
    }

    public function testFromOrderLineItemWithShippingType(): void
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setType('shipping');

        $result = LineItemType::fromOderLineItem($orderLineItem);

        $this->assertSame(LineItemType::DIGITAL, $result);
    }
}