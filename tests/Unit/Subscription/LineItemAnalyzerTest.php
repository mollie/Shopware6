<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
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
            $this->buildRegularLineItem('item-1'),
            $this->buildRegularLineItem('item-2'),
        ]);

        $this->assertFalse($analyzer->hasSubscriptionProduct($items));
    }

    public function testHasSubscriptionProductReturnsTrueWhenAtLeastOneSubscription(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            $this->buildRegularLineItem('item-1'),
            $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS),
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
        $regular = $this->buildRegularLineItem('item-1');
        $first = $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS);
        $second = $this->buildSubscriptionLineItem('sub-2', 2, IntervalUnit::WEEKS);

        $result = $analyzer->getFirstSubscriptionProduct(new LineItemCollection([$regular, $first, $second]));

        $this->assertSame($first, $result);
    }

    public function testGetSubscriptionLineItemsReturnsOnlySubscriptionItems(): void
    {
        $analyzer = new LineItemAnalyzer();
        $regular = $this->buildRegularLineItem('item-1');
        $sub1 = $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS);
        $sub2 = $this->buildSubscriptionLineItem('sub-2', 2, IntervalUnit::WEEKS);

        $result = $analyzer->getSubscriptionLineItems(new LineItemCollection([$regular, $sub1, $sub2]));

        $this->assertSame([$sub1, $sub2], $result);
    }

    public function testGroupSubscriptionLineItemsByIntervalKeysByIntervalString(): void
    {
        $analyzer = new LineItemAnalyzer();
        $sub1month = $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS);
        $sub2months = $this->buildSubscriptionLineItem('sub-2', 1, IntervalUnit::MONTHS);
        $subWeekly = $this->buildSubscriptionLineItem('sub-3', 2, IntervalUnit::WEEKS);
        $regular = $this->buildRegularLineItem('item-1');

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
        $items = new LineItemCollection([
            $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS),
        ]);

        $this->assertFalse($analyzer->hasMixedLineItems($items));
    }

    public function testHasMixedLineItemsReturnsTrueWhenMultipleSubscriptions(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS),
            $this->buildSubscriptionLineItem('sub-2', 2, IntervalUnit::WEEKS),
        ]);

        $this->assertTrue($analyzer->hasMixedLineItems($items));
    }

    public function testHasMixedLineItemsReturnsTrueWhenSubscriptionAndRegularMixed(): void
    {
        $analyzer = new LineItemAnalyzer();
        $items = new LineItemCollection([
            $this->buildSubscriptionLineItem('sub-1', 1, IntervalUnit::MONTHS),
            $this->buildRegularLineItem('item-1'),
        ]);

        $this->assertTrue($analyzer->hasMixedLineItems($items));
    }

    private function buildSubscriptionLineItem(string $id, int $intervalValue, IntervalUnit $intervalUnit): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE);

        $product = new Product();
        $product->setIsSubscription(true);
        $product->setInterval(new Interval($intervalValue, $intervalUnit));

        $lineItem->addExtension(Mollie::EXTENSION, $product);

        return $lineItem;
    }

    private function buildRegularLineItem(string $id): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE);

        $product = new Product();
        $product->setIsSubscription(false);
        $lineItem->addExtension(Mollie::EXTENSION, $product);

        return $lineItem;
    }
}
