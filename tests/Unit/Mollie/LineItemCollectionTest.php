<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

#[CoversClass(LineItemCollection::class)]
final class LineItemCollectionTest extends TestCase
{
    public function testTotalIsTheSumOfTheLineTotalsNotOfTheUnitPrices(): void
    {
        $twoPieces = new LineItem('Product', 2, new Money(9.99, 'EUR'), new Money(19.98, 'EUR'));
        $twoPieces->setShopwareLineItemId('li-1');

        $collection = new LineItemCollection([$twoPieces, $this->mollieLineItem('li-2', 5.0)]);

        $this->assertSame(24.98, $collection->getTotal());
    }

    public function testTotalIsRoundedToTwoDecimalsSoFloatErrorsDoNotReachMollie(): void
    {
        $collection = new LineItemCollection([
            $this->mollieLineItem('li-1', 0.1),
            $this->mollieLineItem('li-2', 0.2),
        ]);

        $this->assertSame(0.3, $collection->getTotal());
    }

    public function testTotalOfAnEmptyCollectionIsZero(): void
    {
        $collection = new LineItemCollection([]);

        $this->assertSame(0.0, $collection->getTotal());
    }

    public function testOnlyLineItemsPresentInTheOrderAreKept(): void
    {
        $collection = new LineItemCollection([
            $this->mollieLineItem('li-1', 10.0),
            $this->mollieLineItem('li-2', 10.0),
        ]);
        $orderLineItems = new OrderLineItemCollection([$this->orderLineItem('li-2')]);

        $filtered = $collection->filterByOrderLineItems($orderLineItems);

        $this->assertCount(1, $filtered);
        $this->assertSame('li-2', $filtered->first()?->getShopwareLineItemId());
    }

    public function testLineItemWithoutShopwareIdIsNeverMatchedAgainstOrderLineItems(): void
    {
        $collection = new LineItemCollection([$this->mollieLineItem('', 10.0)]);
        $orderLineItems = new OrderLineItemCollection([$this->orderLineItem('li-1')]);

        $filtered = $collection->filterByOrderLineItems($orderLineItems);

        $this->assertCount(0, $filtered);
    }

    public function testOnlyLineItemsPresentInTheDeliveriesAreKept(): void
    {
        $collection = new LineItemCollection([
            $this->mollieLineItem('delivery-1', 4.99),
            $this->mollieLineItem('li-1', 10.0),
        ]);
        $deliveries = new OrderDeliveryCollection([$this->orderDelivery('delivery-1')]);

        $filtered = $collection->filterByDeliveries($deliveries);

        $this->assertCount(1, $filtered);
        $this->assertSame('delivery-1', $filtered->first()?->getShopwareLineItemId());
    }

    public function testLineItemWithoutShopwareIdIsNeverMatchedAgainstDeliveries(): void
    {
        $collection = new LineItemCollection([$this->mollieLineItem('', 10.0)]);
        $deliveries = new OrderDeliveryCollection([$this->orderDelivery('delivery-1')]);

        $filtered = $collection->filterByDeliveries($deliveries);

        $this->assertCount(0, $filtered);
    }

    public function testLineItemIsFoundByItsShopwareId(): void
    {
        $collection = new LineItemCollection([
            $this->mollieLineItem('li-1', 10.0),
            $this->mollieLineItem('li-2', 20.0),
        ]);

        $found = $collection->findByShopwareId('li-2');

        $this->assertSame(20.0, $found?->getAmount()->getValue());
    }

    public function testUnknownShopwareIdFindsNoLineItem(): void
    {
        $collection = new LineItemCollection([$this->mollieLineItem('li-1', 10.0)]);

        $this->assertNull($collection->findByShopwareId('li-99'));
    }

    private function mollieLineItem(string $shopwareLineItemId, float $amount): LineItem
    {
        $lineItem = new LineItem('Product', 1, new Money($amount, 'EUR'), new Money($amount, 'EUR'));
        $lineItem->setShopwareLineItemId($shopwareLineItemId);

        return $lineItem;
    }

    private function orderLineItem(string $id): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);

        return $lineItem;
    }

    private function orderDelivery(string $id): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id);

        return $delivery;
    }
}
