<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Product;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Component\Mollie\VoucherCategoryCollection;
use Mollie\Shopware\Entity\Product\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    public function testDefaultState(): void
    {
        $product = new Product();

        $this->assertFalse($product->isSubscription());
        $this->assertSame(0, $product->getRepetition());
        $this->assertInstanceOf(VoucherCategoryCollection::class, $product->getVoucherCategories());
        $this->assertSame(0, $product->getVoucherCategories()->count());
    }

    public function testSetIsSubscription(): void
    {
        $product = new Product();
        $product->setIsSubscription(true);

        $this->assertTrue($product->isSubscription());
    }

    public function testSetAndGetInterval(): void
    {
        $product = new Product();
        $interval = new Interval(3, IntervalUnit::MONTHS);
        $product->setInterval($interval);

        $this->assertSame($interval, $product->getInterval());
    }

    public function testSetAndGetRepetition(): void
    {
        $product = new Product();
        $product->setRepetition(12);

        $this->assertSame(12, $product->getRepetition());
    }

    public function testSetAndGetVoucherCategories(): void
    {
        $product = new Product();
        $collection = new VoucherCategoryCollection([VoucherCategory::MEAL]);
        $product->setVoucherCategories($collection);

        $this->assertSame($collection, $product->getVoucherCategories());
    }

    public function testCreateFromCustomFieldsWithSubscription(): void
    {
        $customFields = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 2,
            'mollie_payments_product_subscription_interval_unit' => 'weeks',
            'mollie_payments_product_subscription_repetition' => 6,
        ];

        $product = Product::createFromCustomFields($customFields);

        $this->assertTrue($product->isSubscription());
        $this->assertSame(2, $product->getInterval()->getIntervalValue());
        $this->assertSame(IntervalUnit::WEEKS, $product->getInterval()->getIntervalUnit());
        $this->assertSame(6, $product->getRepetition());
    }

    public function testCreateFromCustomFieldsWithoutSubscription(): void
    {
        $customFields = [
            'mollie_payments_product_subscription_enabled' => false,
        ];

        $product = Product::createFromCustomFields($customFields);

        $this->assertFalse($product->isSubscription());
    }

    public function testCreateFromCustomFieldsWithVoucherType(): void
    {
        $customFields = [
            'mollie_payments_product_voucher_type' => [1, 2],
        ];

        $product = Product::createFromCustomFields($customFields);

        $this->assertSame(2, $product->getVoucherCategories()->count());
    }

    public function testCreateFromCustomFieldsWithSingleVoucherType(): void
    {
        $customFields = [
            'mollie_payments_product_voucher_type' => 3,
        ];

        $product = Product::createFromCustomFields($customFields);

        $this->assertSame(1, $product->getVoucherCategories()->count());
    }

    public function testCreateFromCustomFieldsWithInvalidVoucherTypeIsIgnored(): void
    {
        $customFields = [
            'mollie_payments_product_voucher_type' => [999],
        ];

        $product = Product::createFromCustomFields($customFields);

        $this->assertSame(0, $product->getVoucherCategories()->count());
    }

    public function testCreateFromEmptyCustomFields(): void
    {
        $product = Product::createFromCustomFields([]);

        $this->assertFalse($product->isSubscription());
        $this->assertSame(0, $product->getVoucherCategories()->count());
    }
}
