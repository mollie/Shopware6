<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Struct;

use Mollie\Shopware\Component\Refund\Struct\CartItemPromotionStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartItemPromotionStruct::class)]
final class CartItemPromotionStructTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $struct = new CartItemPromotionStruct();

        $this->assertSame(0.0, $struct->getDiscount());
        $this->assertSame(0, $struct->getQuantity());
        $this->assertSame(0.0, $struct->getTaxValue());
    }

    public function testGetters(): void
    {
        $struct = new CartItemPromotionStruct(5.0, 2, 0.95);

        $this->assertSame(5.0, $struct->getDiscount());
        $this->assertSame(2, $struct->getQuantity());
        $this->assertSame(0.95, $struct->getTaxValue());
    }

    public function testValuesAreRounded(): void
    {
        $struct = new CartItemPromotionStruct(5.555, 1, 0.955);

        $this->assertSame(5.56, $struct->getDiscount());
        $this->assertSame(0.96, $struct->getTaxValue());
    }

    public function testJsonSerializeContainsAllProperties(): void
    {
        $struct = new CartItemPromotionStruct(3.0, 1, 0.57);

        $data = $struct->jsonSerialize();

        $this->assertArrayHasKey('discount', $data);
        $this->assertArrayHasKey('quantity', $data);
        $this->assertArrayHasKey('taxValue', $data);
        $this->assertSame(3.0, $data['discount']);
        $this->assertSame(1, $data['quantity']);
        $this->assertSame(0.57, $data['taxValue']);
    }
}
