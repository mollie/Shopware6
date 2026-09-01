<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Struct;

use Mollie\Shopware\Component\Refund\Struct\RefundTotalsStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundTotalsStruct::class)]
final class RefundTotalsStructTest extends TestCase
{
    public function testAFreshOverviewStartsWithoutAnyAmount(): void
    {
        $totals = new RefundTotalsStruct();

        $this->assertSame(0.0, $totals->getRemaining());
        $this->assertSame(0.0, $totals->getRefunded());
        $this->assertSame(0.0, $totals->getVoucherAmount());
        $this->assertSame(0.0, $totals->getPendingRefunds());
        $this->assertSame(0.0, $totals->getRoundingDiff());
    }

    public function testEveryAmountIsRoundedToTwoDecimalsSoNoFloatErrorReachesTheAdmin(): void
    {
        $totals = new RefundTotalsStruct();

        $totals->setRemaining(10.005);
        $totals->setRefunded(0.1 + 0.2);
        $totals->setVoucherAmount(5.554);
        $totals->setPendingRefunds(1.239);
        $totals->setRoundingDiff(0.014);

        $this->assertSame(10.01, $totals->getRemaining());
        $this->assertSame(0.3, $totals->getRefunded());
        $this->assertSame(5.55, $totals->getVoucherAmount());
        $this->assertSame(1.24, $totals->getPendingRefunds());
        $this->assertSame(0.01, $totals->getRoundingDiff());
    }

    public function testANegativeRoundingDifferenceIsKept(): void
    {
        $totals = new RefundTotalsStruct();

        $totals->setRoundingDiff(-0.014);

        $this->assertSame(-0.01, $totals->getRoundingDiff());
    }
}
