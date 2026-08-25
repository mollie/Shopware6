<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Product;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Component\Mollie\VoucherCategoryCollection;
use Mollie\Shopware\Entity\Product\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    public function testProductIsNoSubscriptionByDefault(): void
    {
        $product = new Product();

        self::assertFalse($product->isSubscription());
        self::assertFalse($product->allowsStandalonePurchase());
        self::assertSame(0, $product->getRepetition());
        self::assertCount(0, $product->getVoucherCategories());
    }

    public function testVoucherCategoriesAreKept(): void
    {
        $product = new Product();
        $product->setVoucherCategories(new VoucherCategoryCollection([VoucherCategory::MEAL]));

        self::assertSame([VoucherCategory::MEAL], array_values($product->getVoucherCategories()->getElements()));
    }

    public function testSubscriptionValuesAreKept(): void
    {
        $interval = new Interval(3, IntervalUnit::WEEKS);

        $product = new Product();
        $product->setIsSubscription(true);
        $product->setInterval($interval);
        $product->setRepetition(12);
        $product->setAllowStandalonePurchase(true);

        self::assertTrue($product->isSubscription());
        self::assertSame($interval, $product->getInterval());
        self::assertSame(12, $product->getRepetition());
        self::assertTrue($product->allowsStandalonePurchase());
    }

    public function testCustomFieldsWithoutMollieDataProduceAPlainProduct(): void
    {
        $product = Product::createFromCustomFields([]);

        self::assertFalse($product->isSubscription());
        self::assertCount(0, $product->getVoucherCategories());
    }

    public function testVoucherTypesAreReadFromTheCustomFields(): void
    {
        $product = Product::createFromCustomFields([
            'mollie_payments_product_voucher_type' => [1, 3],
        ]);

        self::assertSame(
            [VoucherCategory::ECO, VoucherCategory::MEAL],
            array_values($product->getVoucherCategories()->getElements())
        );
    }

    public function testASingleVoucherTypeDoesNotHaveToBeAnArray(): void
    {
        $product = Product::createFromCustomFields([
            'mollie_payments_product_voucher_type' => 2,
        ]);

        self::assertSame([VoucherCategory::GIFT], array_values($product->getVoucherCategories()->getElements()));
    }

    public function testUnknownVoucherTypesAreIgnored(): void
    {
        $product = Product::createFromCustomFields([
            'mollie_payments_product_voucher_type' => [99],
        ]);

        self::assertCount(0, $product->getVoucherCategories());
    }

    public function testSubscriptionIsReadFromTheCustomFields(): void
    {
        $product = Product::createFromCustomFields([
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 2,
            'mollie_payments_product_subscription_interval_unit' => 'months',
            'mollie_payments_product_subscription_repetition' => 6,
            'mollie_payments_product_subscription_allow_onetime' => true,
        ]);

        self::assertTrue($product->isSubscription());
        self::assertSame('2 months', (string) $product->getInterval());
        self::assertSame(6, $product->getRepetition());
        self::assertTrue($product->allowsStandalonePurchase());
    }

    /**
     * @param array<string, mixed> $customFields
     */
    #[DataProvider('incompleteSubscriptionCustomFields')]
    public function testIncompleteSubscriptionSettingsDoNotMakeAProductASubscription(array $customFields): void
    {
        $product = Product::createFromCustomFields($customFields);

        self::assertFalse($product->isSubscription());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function incompleteSubscriptionCustomFields(): array
    {
        return [
            'switched off' => [[
                'mollie_payments_product_subscription_enabled' => false,
                'mollie_payments_product_subscription_interval' => 2,
                'mollie_payments_product_subscription_interval_unit' => 'months',
            ]],
            'without an interval' => [[
                'mollie_payments_product_subscription_enabled' => true,
                'mollie_payments_product_subscription_interval' => 0,
                'mollie_payments_product_subscription_interval_unit' => 'months',
            ]],
            'without an interval unit' => [[
                'mollie_payments_product_subscription_enabled' => true,
                'mollie_payments_product_subscription_interval' => 2,
                'mollie_payments_product_subscription_interval_unit' => '',
            ]],
        ];
    }
}
