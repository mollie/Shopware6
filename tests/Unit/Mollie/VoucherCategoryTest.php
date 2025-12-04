<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\VoucherCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VoucherCategory::class)]
final class VoucherCategoryTest extends TestCase
{
    public function testTryFromNumberReturnsCorrectCategory(): void
    {
        $this->assertSame(VoucherCategory::ECO, VoucherCategory::tryFromNumber(1));
        $this->assertSame(VoucherCategory::GIFT, VoucherCategory::tryFromNumber(2));
        $this->assertSame(VoucherCategory::MEAL, VoucherCategory::tryFromNumber(3));
    }

    public function testTryFromNumberReturnsNullForInvalidNumber(): void
    {
        $this->assertNull(VoucherCategory::tryFromNumber(0));
        $this->assertNull(VoucherCategory::tryFromNumber(4));
        $this->assertNull(VoucherCategory::tryFromNumber(99));
        $this->assertNull(VoucherCategory::tryFromNumber(-1));
    }
}