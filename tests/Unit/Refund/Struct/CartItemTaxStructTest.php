<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Struct;

use Mollie\Shopware\Component\Refund\Struct\CartItemTaxStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartItemTaxStruct::class)]
final class CartItemTaxStructTest extends TestCase
{
    public function testGetters(): void
    {
        $struct = new CartItemTaxStruct(1.5, 0.75, 0.0);

        $this->assertSame(1.5, $struct->getTotalItemTax());
        $this->assertSame(0.75, $struct->getPerItemTax());
        $this->assertSame(0.0, $struct->getTotalToPerItemRoundingDiff());
    }

    #[DataProvider('roundingProvider')]
    public function testValuesAreRounded(float $input, float $expected): void
    {
        $struct = new CartItemTaxStruct($input, $input, $input);

        $this->assertSame($expected, $struct->getTotalItemTax());
        $this->assertSame($expected, $struct->getPerItemTax());
        $this->assertSame($expected, $struct->getTotalToPerItemRoundingDiff());
    }

    public static function roundingProvider(): array
    {
        return [
            'rounds up at third decimal' => [1.555, 1.56],
            'rounds down' => [1.554, 1.55],
            'already two decimals' => [1.50, 1.5],
        ];
    }

    public function testJsonSerializeContainsAllProperties(): void
    {
        $struct = new CartItemTaxStruct(2.0, 1.0, 0.01);

        $data = $struct->jsonSerialize();

        $this->assertArrayHasKey('totalItemTax', $data);
        $this->assertArrayHasKey('perItemTax', $data);
        $this->assertArrayHasKey('totalToPerItemRoundingDiff', $data);
        $this->assertSame(2.0, $data['totalItemTax']);
        $this->assertSame(1.0, $data['perItemTax']);
        $this->assertSame(0.01, $data['totalToPerItemRoundingDiff']);
    }
}
