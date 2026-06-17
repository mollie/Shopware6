<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

#[CoversClass(LineItemAnalyzer::class)]
final class LineItemAnalyzerTest extends TestCase
{
    public function testHasSubscriptionProductReturnsFalseForEmptyCollection(): void
    {
        $analyzer = new LineItemAnalyzer();

        $this->assertFalse($analyzer->hasSubscriptionProduct(new LineItemCollection()));
    }

    public function testHasSubscriptionProductReturnsFalseWhenOnlyRegularItems(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            LineItemBuilder::regular('item-1')->build(),
            LineItemBuilder::regular('item-2')->build(),
        ]);

        $this->assertFalse($analyzer->hasSubscriptionProduct($items));
    }

    public function testHasSubscriptionProductReturnsTrueWhenAtLeastOneSubscription(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            LineItemBuilder::regular('item-1')->build(),
            LineItemBuilder::subscription('sub-1')->build(),
        ]);

        $this->assertTrue($analyzer->hasSubscriptionProduct($items));
    }

    public function testGetFirstSubscriptionProductReturnsNullWhenNoneFound(): void
    {
        $analyzer = new LineItemAnalyzer();

        $this->assertNull($analyzer->getFirstSubscriptionProduct(new LineItemCollection()));
    }

    public function testGetFirstSubscriptionProductReturnsFirstSubscriptionLineItem(): void
    {
        $analyzer = new LineItemAnalyzer();
        $regular = LineItemBuilder::regular('item-1')->build();
        $first = LineItemBuilder::subscription('sub-1', 1, IntervalUnit::MONTHS)->build();
        $second = LineItemBuilder::subscription('sub-2', 2, IntervalUnit::WEEKS)->build();

        $result = $analyzer->getFirstSubscriptionProduct(new LineItemCollection([$regular, $first, $second]));

        $this->assertSame($first, $result);
    }

    public function testGetSubscriptionLineItemsReturnsOnlySubscriptionItems(): void
    {
        $analyzer = new LineItemAnalyzer();
        $regular = LineItemBuilder::regular('item-1')->build();
        $sub1 = LineItemBuilder::subscription('sub-1', 1, IntervalUnit::MONTHS)->build();
        $sub2 = LineItemBuilder::subscription('sub-2', 2, IntervalUnit::WEEKS)->build();

        $result = $analyzer->getSubscriptionLineItems(new LineItemCollection([$regular, $sub1, $sub2]));

        $this->assertSame([$sub1, $sub2], $result);
    }

    public function testGroupSubscriptionLineItemsByIntervalKeysByIntervalString(): void
    {
        $analyzer = new LineItemAnalyzer();
        $sub1month = LineItemBuilder::subscription('sub-1', 1, IntervalUnit::MONTHS)->build();
        $sub2months = LineItemBuilder::subscription('sub-2', 1, IntervalUnit::MONTHS)->build();
        $subWeekly = LineItemBuilder::subscription('sub-3', 2, IntervalUnit::WEEKS)->build();
        $regular = LineItemBuilder::regular('item-1')->build();

        $groups = $analyzer->groupSubscriptionLineItemsByInterval(
            new LineItemCollection([$sub1month, $sub2months, $subWeekly, $regular])
        );

        $this->assertSame(['1 months', '2 weeks'], array_keys($groups));
        $this->assertSame([$sub1month, $sub2months], $groups['1 months']);
        $this->assertSame([$subWeekly], $groups['2 weeks']);
    }

    public function testHasMixedLineItemsReturnsFalseForSingleSubscriptionOnly(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([LineItemBuilder::subscription('sub-1')->build()]);

        $this->assertFalse($analyzer->hasMixedLineItems($items));
    }

    public function testHasMixedLineItemsReturnsTrueWhenMultipleSubscriptions(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            LineItemBuilder::subscription('sub-1', 1, IntervalUnit::MONTHS)->build(),
            LineItemBuilder::subscription('sub-2', 2, IntervalUnit::WEEKS)->build(),
        ]);

        $this->assertTrue($analyzer->hasMixedLineItems($items));
    }

    public function testHasMixedLineItemsReturnsTrueWhenSubscriptionAndRegularMixed(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            LineItemBuilder::subscription('sub-1')->build(),
            LineItemBuilder::regular('item-1')->build(),
        ]);

        $this->assertTrue($analyzer->hasMixedLineItems($items));
    }
}
