<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use PHPUnit\Framework\TestCase;

class MollieOrderPriceBuilderTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('EUR', MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE);
        self::assertSame(2, MollieOrderPriceBuilder::MOLLIE_PRICE_PRECISION);
    }

    public function testEmptyPrice(): void
    {
        $currency = 'EUR';
        $expected = [
            'currency' => $currency,
            'value' => '0.00'
        ];

        self::assertSame($expected, (new MollieOrderPriceBuilder())->build(null, $currency));
    }

    public function testFallbackCurrency(): void
    {
        $price = 1342.12;

        $expected = [
            'currency' => MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE,
            'value' => (string)$price
        ];

        self::assertSame($expected, (new MollieOrderPriceBuilder())->build($price, null));
    }

    public function testBuildRounding(): void
    {
        $price = 1.346;
        $currency = 'USD';

        $expected = [
            'currency' => $currency,
            'value' => '1.35'
        ];

        self::assertSame($expected, (new MollieOrderPriceBuilder())->build($price, $currency));
    }
}
