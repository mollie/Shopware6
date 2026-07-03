<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Struct;

use Mollie\Shopware\Component\Refund\Struct\CartItemShopwareStruct;
use Mollie\Shopware\Component\Refund\Struct\CartItemStruct;
use Mollie\Shopware\Component\Refund\Struct\CartItemTaxStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartItemStruct::class)]
final class CartItemStructTest extends TestCase
{
    private CartItemShopwareStruct $shopware;

    protected function setUp(): void
    {
        $this->shopware = new CartItemShopwareStruct(
            'item-id', 'Label', 10.0, 1, 10.0, 10.0, 'SW-1', false, false,
            new CartItemTaxStruct(1.6, 1.6, 0.0),
        );
    }

    public function testRefundedDefaultsToZero(): void
    {
        $struct = new CartItemStruct($this->shopware);

        $this->assertSame(0, $struct->getRefunded());
    }

    public function testSetRefunded(): void
    {
        $struct = new CartItemStruct($this->shopware);
        $struct->setRefunded(3);

        $this->assertSame(3, $struct->getRefunded());
    }

    public function testGetShopware(): void
    {
        $struct = new CartItemStruct($this->shopware);

        $this->assertSame($this->shopware, $struct->getShopware());
    }

    public function testJsonSerializeContainsShopwareAndRefunded(): void
    {
        $struct = new CartItemStruct($this->shopware);

        $data = $struct->jsonSerialize();

        $this->assertArrayHasKey('shopware', $data);
        $this->assertArrayHasKey('refunded', $data);
        $this->assertSame(0, $data['refunded']);
    }
}
