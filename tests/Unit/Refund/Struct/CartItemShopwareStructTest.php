<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Struct;

use Mollie\Shopware\Component\Refund\Struct\CartItemPromotionStruct;
use Mollie\Shopware\Component\Refund\Struct\CartItemShopwareStruct;
use Mollie\Shopware\Component\Refund\Struct\CartItemTaxStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartItemShopwareStruct::class)]
final class CartItemShopwareStructTest extends TestCase
{
    private CartItemTaxStruct $tax;

    protected function setUp(): void
    {
        $this->tax = new CartItemTaxStruct(1.9, 1.9, 0.0);
    }

    public function testGetters(): void
    {
        $struct = new CartItemShopwareStruct(
            'item-id',
            'Product Label',
            9.99,
            2,
            19.98,
            19.98,
            'SW-1000',
            false,
            false,
            $this->tax,
        );

        $this->assertSame('item-id', $struct->getId());
        $this->assertSame('Product Label', $struct->getLabel());
        $this->assertSame(9.99, $struct->getUnitPrice());
        $this->assertSame(2, $struct->getQuantity());
        $this->assertSame(19.98, $struct->getTotalPrice());
        $this->assertSame(19.98, $struct->getDiscountedPrice());
        $this->assertSame('SW-1000', $struct->getProductNumber());
        $this->assertFalse($struct->isPromotion());
        $this->assertFalse($struct->isDelivery());
        $this->assertSame($this->tax, $struct->getTax());
    }

    public function testDefaultPromotionStructWhenNotProvided(): void
    {
        $struct = new CartItemShopwareStruct(
            'item-id', 'Label', 10.0, 1, 10.0, 10.0, 'SW-1', false, false, $this->tax,
        );

        $promotion = $struct->getPromotion();

        $this->assertInstanceOf(CartItemPromotionStruct::class, $promotion);
        $this->assertSame(0.0, $promotion->getDiscount());
        $this->assertSame(0, $promotion->getQuantity());
        $this->assertSame(0.0, $promotion->getTaxValue());
    }

    public function testPricesAreRounded(): void
    {
        $struct = new CartItemShopwareStruct(
            'id', 'Label', 9.999, 1, 9.999, 9.999, 'SW-1', false, false, $this->tax,
        );

        $this->assertSame(10.0, $struct->getUnitPrice());
        $this->assertSame(10.0, $struct->getTotalPrice());
        $this->assertSame(10.0, $struct->getDiscountedPrice());
    }

    public function testShippingConstant(): void
    {
        $this->assertSame('SHIPPING', CartItemShopwareStruct::SHIPPING);
    }

    public function testJsonSerializeContainsAllProperties(): void
    {
        $struct = new CartItemShopwareStruct(
            'item-id', 'Label', 5.0, 1, 5.0, 5.0, 'SW-1', true, false, $this->tax,
        );

        $data = $struct->jsonSerialize();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('label', $data);
        $this->assertArrayHasKey('unitPrice', $data);
        $this->assertArrayHasKey('quantity', $data);
        $this->assertArrayHasKey('totalPrice', $data);
        $this->assertArrayHasKey('discountedPrice', $data);
        $this->assertArrayHasKey('productNumber', $data);
        $this->assertArrayHasKey('isPromotion', $data);
        $this->assertArrayHasKey('isDelivery', $data);
        $this->assertArrayHasKey('tax', $data);
        $this->assertArrayHasKey('promotion', $data);
        $this->assertTrue($data['isPromotion']);
        $this->assertFalse($data['isDelivery']);
    }
}
