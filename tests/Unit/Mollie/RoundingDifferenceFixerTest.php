<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoundingDifferenceFixer::class)]
final class RoundingDifferenceFixerTest extends TestCase
{
    public function testNoDifferenceKeepsLineItemsUntouched(): void
    {
        $lines = new LineItemCollection([$this->lineItem(33.34), $this->lineItem(66.66)]);

        $fixer = new RoundingDifferenceFixer();
        $result = $fixer->fixAmountDiff(new Money(100.00, 'EUR'), $lines, '', '');

        $this->assertCount(2, $result);
    }

    public function testPositiveDifferenceIsAddedAsSurcharge(): void
    {
        $lines = new LineItemCollection([$this->lineItem(33.33), $this->lineItem(66.66)]);

        $fixer = new RoundingDifferenceFixer();
        $result = $fixer->fixAmountDiff(new Money(100.00, 'EUR'), $lines, '', '');

        $this->assertCount(3, $result);

        $roundingLine = $result->last();
        $this->assertSame(RoundingDifferenceFixer::DEFAULT_TITLE, $roundingLine->getDescription());
        $this->assertSame(LineItemType::SURCHARGE, $roundingLine->getType());
        $this->assertSame(0.01, $roundingLine->getAmount()->getValue());
        $this->assertSame('rounding', $roundingLine->getMetadata()['type']);
    }

    public function testNegativeDifferenceIsAddedAsDiscount(): void
    {
        $lines = new LineItemCollection([$this->lineItem(50.00), $this->lineItem(50.01)]);

        $fixer = new RoundingDifferenceFixer();
        $result = $fixer->fixAmountDiff(new Money(100.00, 'EUR'), $lines, '', '');

        $this->assertCount(3, $result);

        $roundingLine = $result->last();
        $this->assertSame(LineItemType::DISCOUNT, $roundingLine->getType());
        $this->assertSame(-0.01, $roundingLine->getAmount()->getValue());
    }

    public function testCustomTitleAndSkuAreApplied(): void
    {
        $lines = new LineItemCollection([$this->lineItem(33.33), $this->lineItem(66.66)]);

        $fixer = new RoundingDifferenceFixer();
        $result = $fixer->fixAmountDiff(new Money(100.00, 'EUR'), $lines, 'My DIFF', 'sku-123');

        $roundingLine = $result->last();
        $this->assertSame('My DIFF', $roundingLine->getDescription());
        $this->assertSame('sku-123', $roundingLine->getSku());
    }

    public function testZeroDecimalCurrencyUsesIntegerPrecision(): void
    {
        $lines = new LineItemCollection([$this->lineItem(2500.0, 'JPY'), $this->lineItem(2499.0, 'JPY')]);

        $fixer = new RoundingDifferenceFixer();
        $result = $fixer->fixAmountDiff(new Money(5000.0, 'JPY'), $lines, '', '');

        $this->assertCount(3, $result);
        $this->assertSame(1.0, $result->last()->getAmount()->getValue());
        $this->assertSame('JPY', $result->last()->getAmount()->getCurrency());
    }

    private function lineItem(float $amount, string $currency = 'EUR'): LineItem
    {
        $money = new Money($amount, $currency);

        return new LineItem('Product', 1, $money, $money);
    }
}
