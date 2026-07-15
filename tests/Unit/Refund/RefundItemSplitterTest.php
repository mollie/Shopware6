<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Refund\RefundItemSplitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundItemSplitter::class)]
final class RefundItemSplitterTest extends TestCase
{
    private RefundItemSplitter $splitter;

    protected function setUp(): void
    {
        $this->splitter = new RefundItemSplitter();
    }

    public function testPartialAmountAcrossTwoUnitsSplitsIntoUnitAndRemainder(): void
    {
        // 2 x 29.90 = 59.80, refund 32 -> one full unit (29.90) + remainder (2.10)
        $split = $this->splitter->split(32.0, 59.80, 2, 0.0);

        $this->assertSame(1, $split['fullUnits']);
        $this->assertSame(29.90, $split['unitPrice']);
        $this->assertSame(2.10, $split['remainder']);
        $this->assertSame(0.0, $split['excess']);
    }

    public function testFullLineRefundsAllUnitsWithoutRemainder(): void
    {
        $split = $this->splitter->split(59.80, 59.80, 2, 0.0);

        $this->assertSame(2, $split['fullUnits']);
        $this->assertSame(0.0, $split['remainder']);
        $this->assertSame(0.0, $split['excess']);
    }

    public function testPartialAmountBelowUnitPriceIsRemainderOnly(): void
    {
        $split = $this->splitter->split(5.0, 59.80, 2, 0.0);

        $this->assertSame(0, $split['fullUnits']);
        $this->assertSame(5.0, $split['remainder']);
        $this->assertSame(0.0, $split['excess']);
    }

    public function testSingleUnitFullRefund(): void
    {
        $split = $this->splitter->split(29.90, 29.90, 1, 0.0);

        $this->assertSame(1, $split['fullUnits']);
        $this->assertSame(0.0, $split['remainder']);
        $this->assertSame(0.0, $split['excess']);
    }

    public function testAmountBeyondLineMaxBecomesExcess(): void
    {
        // single unit worth 29.90, refund 32 -> 29.90 on the line, 2.10 excess
        $split = $this->splitter->split(32.0, 29.90, 1, 0.0);

        $this->assertSame(1, $split['fullUnits']);
        $this->assertSame(0.0, $split['remainder']);
        $this->assertSame(2.10, $split['excess']);
    }

    public function testAlreadyRefundedReducesRemainingAndUnits(): void
    {
        // one unit (29.90) already refunded, refund 32 more of a 59.80 line
        // -> line remaining is 29.90, so one more full unit, 2.10 excess
        $split = $this->splitter->split(32.0, 59.80, 2, 29.90);

        $this->assertSame(1, $split['fullUnits']);
        $this->assertSame(0.0, $split['remainder']);
        $this->assertSame(2.10, $split['excess']);
    }

    public function testRemainingPartialAmountAfterEarlierPartialRefund(): void
    {
        // 5.00 already refunded on a single 29.90 unit, refund the rest (24.90)
        $split = $this->splitter->split(24.90, 29.90, 1, 5.0);

        $this->assertSame(0, $split['fullUnits']);
        $this->assertSame(24.90, $split['remainder']);
        $this->assertSame(0.0, $split['excess']);
    }

    public function testNoUnitPriceTreatsAmountAsRemainder(): void
    {
        // quantity 0 (e.g. no usable unit price) -> whole amount is a partial entry
        $split = $this->splitter->split(4.99, 4.99, 0, 0.0);

        $this->assertSame(0, $split['fullUnits']);
        $this->assertSame(4.99, $split['remainder']);
        $this->assertSame(0.0, $split['excess']);
    }
}
