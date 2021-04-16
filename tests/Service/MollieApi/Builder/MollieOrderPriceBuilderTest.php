<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use PHPUnit\Framework\TestCase;

class MollieOrderPriceBuilderTest extends TestCase
{
    /**
     * @param array $expected
     * @param float|null $price
     * @param string|null $currency
     * @dataProvider getTestData
     */
    public function testBuild(array $expected, ?float $price, ?string $currency): void
    {
        $builder = new MollieOrderPriceBuilder();
        self::assertSame($expected, $builder->build($price, $currency));
    }

    public function testConstants(): void
    {
        self::assertSame('EUR', MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE);
        self::assertSame(2, MollieOrderPriceBuilder::MOLLIE_PRICE_PRECISION);
    }

    public function getTestData(): array
    {
        return [
            'no values set' => [
                ['currency' => MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE, 'value' => '0.00'],
                null,
                null
            ],
            'rounding at second decimal' => [
                ['currency' => 'foo', 'value' => '3.48'],
                3.478,
                'foo'
            ],
            'always two decimals' => [
                ['currency' => MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE, 'value' => '3.40'],
                3.4,
                null
            ],
        ];
    }
}
