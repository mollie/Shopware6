<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Order\Admin;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Order\Admin\OrderAdminStatusBuilder;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(OrderAdminStatusBuilder::class)]
final class OrderAdminStatusBuilderTest extends TestCase
{
    private OrderAdminStatusBuilder $builder;

    private OrderEntityBuilder $orderBuilder;

    protected function setUp(): void
    {
        $lineItemFilter = LineItemFilterBuilder::build();
        $itemResolver = new ShipmentItemResolver($lineItemFilter);
        $this->builder = new OrderAdminStatusBuilder($itemResolver, $lineItemFilter);
        $this->orderBuilder = new OrderEntityBuilder();
    }

    public function testBuildShippingTotalForPaymentsApiCountsOpenItemsAsShippable(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 3, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));

        $total = $this->builder->buildShippingTotal(null, $order, true);

        self::assertSame('0.00', $total->amount);
        self::assertSame(0, $total->quantity);
        self::assertSame(3, $total->shippable);
    }

    public function testBuildShippingTotalForPaymentsApiDerivesShippedItemsFromCustomFields(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 3, 10.0, ['quantity' => 1]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));

        $total = $this->builder->buildShippingTotal(null, $order, true);

        self::assertSame('10.00', $total->amount);
        self::assertSame(1, $total->quantity);
        self::assertSame(2, $total->shippable);
    }

    public function testBuildShippingTotalForPaymentsApiHasNoShippableItemsWhenShippingNotAllowed(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 3, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));

        $total = $this->builder->buildShippingTotal(null, $order, false);

        self::assertSame(0, $total->shippable);
    }

    public function testBuildShippingTotalAmountHasNoThousandsSeparator(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 2, 1500.0, ['quantity' => 1]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));

        $total = $this->builder->buildShippingTotal(null, $order, true);

        self::assertSame('1500.00', $total->amount);
    }

    public function testCancelStatusForPaymentsApiDerivesFromShopwareLineItems(): void
    {
        $lineItems = new OrderLineItemCollection([$this->shopwareLineItem('li1', 2)]);

        $status = $this->builder->buildCancelStatus('', null, $lineItems, true);

        self::assertArrayHasKey('li1', $status);
        self::assertTrue($status['li1']->isCancelable);
        self::assertSame(2, $status['li1']->cancelableQuantity);
        self::assertSame(0, $status['li1']->quantityCanceled);
    }

    public function testCancelStatusIsNotCancelableWhenShippingNotAllowed(): void
    {
        $lineItems = new OrderLineItemCollection([$this->shopwareLineItem('li1', 2)]);

        $status = $this->builder->buildCancelStatus('', null, $lineItems, false);

        self::assertFalse($status['li1']->isCancelable);
        self::assertSame(0, $status['li1']->cancelableQuantity);
    }

    public function testShippingStatusForPaymentsApiDerivesFromShopwareLineItems(): void
    {
        $lineItems = new OrderLineItemCollection([$this->shopwareLineItem('li1', 3)]);

        $status = $this->builder->buildShippingStatus('', null, $lineItems, true);

        self::assertArrayHasKey('li1', $status);
        self::assertTrue($status['li1']->isShippable);
        self::assertSame(3, $status['li1']->shippableQuantity);
        self::assertSame(0, $status['li1']->quantityShipped);
    }

    public function testShippingStatusForPaymentsApiSkipsContainerLineItems(): void
    {
        $container = $this->orderBuilder->createContainerLineItem('container1', 'Personalize this product', 10.0);
        $product = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 1, 10.0);
        $lineItems = new OrderLineItemCollection([$container, $product]);

        $status = $this->builder->buildShippingStatus('', null, $lineItems, true);

        self::assertArrayNotHasKey('container1', $status);
        self::assertArrayHasKey('li1', $status);
    }

    public function testShippingStatusForOrdersApiDerivesFromMollieOrderLines(): void
    {
        $line = new LineItem('product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setId('mollie-line-1');
        $line->setShopwareLineItemId('sw1');
        $line->setShippableQuantity(2);
        $line->setQuantityShipped(1);
        $mollieOrder = new Order('ord-1', '', null, [$line]);

        $status = $this->builder->buildShippingStatus('ord-1', $mollieOrder, null, true);

        self::assertArrayHasKey('sw1', $status);
        self::assertTrue($status['sw1']->isShippable);
        self::assertSame(2, $status['sw1']->shippableQuantity);
        self::assertSame(1, $status['sw1']->quantityShipped);
        self::assertSame('mollie-line-1', $status['sw1']->mollieId);
    }

    public function testCancelStatusIsEmptyForAnOrderWithoutLoadedLineItems(): void
    {
        self::assertSame([], $this->builder->buildCancelStatus('', null, null, true));
    }

    public function testCancelStatusSkipsContainerLineItems(): void
    {
        $container = $this->orderBuilder->createContainerLineItem('container1', 'Personalize this product', 10.0);
        $product = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 1, 10.0);

        $status = $this->builder->buildCancelStatus('', null, new OrderLineItemCollection([$container, $product]), true);

        self::assertArrayNotHasKey('container1', $status);
        self::assertArrayHasKey('li1', $status);
    }

    public function testCancelStatusForOrdersApiDerivesFromMollieOrderLines(): void
    {
        $line = new LineItem('product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setId('mollie-line-1');
        $line->setShopwareLineItemId('sw1');
        $line->setCancelableQuantity(2);
        $line->setQuantityCanceled(1);

        $status = $this->builder->buildCancelStatus('ord-1', new Order('ord-1', '', null, [$line]), null, true);

        self::assertTrue($status['sw1']->isCancelable);
        self::assertSame(2, $status['sw1']->cancelableQuantity);
        self::assertSame(1, $status['sw1']->quantityCanceled);
        self::assertSame('ord-1', $status['sw1']->mollieOrderId);
        self::assertSame('mollie-line-1', $status['sw1']->mollieId);
    }

    public function testCancelStatusSkipsMollieLinesWithoutAShopwareLineItem(): void
    {
        // The rounding-difference line only exists at Mollie, so the admin has no row to attach it to.
        $status = $this->builder->buildCancelStatus('ord-1', $this->mollieOrderWithRoundingLine(), null, true);

        self::assertSame([], $status);
    }

    public function testShippingStatusSkipsMollieLinesWithoutAShopwareLineItem(): void
    {
        $status = $this->builder->buildShippingStatus('ord-1', $this->mollieOrderWithRoundingLine(), null, true);

        self::assertSame([], $status);
    }

    public function testShippingStatusIsEmptyForAnOrderWithoutLoadedLineItems(): void
    {
        self::assertSame([], $this->builder->buildShippingStatus('', null, null, true));
    }

    public function testShippingStatusForPaymentsApiIncludesTheShippingCosts(): void
    {
        $delivery = $this->orderBuilder->createShippableDelivery('delivery1', 'li1');

        $status = $this->builder->buildShippingStatus(
            '',
            null,
            new OrderLineItemCollection(),
            true,
            new OrderDeliveryCollection([$delivery]),
        );

        self::assertArrayHasKey('delivery1', $status);
        self::assertTrue($status['delivery1']->isShippable);
        self::assertSame(1, $status['delivery1']->shippableQuantity);
        self::assertSame(0, $status['delivery1']->quantityShipped);
    }

    public function testAlreadyShippedShippingCostsAreNotShippableAgain(): void
    {
        $delivery = $this->orderBuilder->createShippableDelivery('delivery1', 'li1', 4.99, ['quantity' => 1]);

        $status = $this->builder->buildShippingStatus(
            '',
            null,
            new OrderLineItemCollection(),
            true,
            new OrderDeliveryCollection([$delivery]),
        );

        self::assertFalse($status['delivery1']->isShippable);
        self::assertSame(0, $status['delivery1']->shippableQuantity);
        self::assertSame(1, $status['delivery1']->quantityShipped);
    }

    public function testShippingTotalForOrdersApiAggregatesTheMollieOrderLines(): void
    {
        $first = new LineItem('product', 2, new Money(10.0, 'EUR'), new Money(20.0, 'EUR'));
        $first->setShopwareLineItemId('sw1');
        $first->setQuantityShipped(1);
        $first->setShippableQuantity(1);
        $first->setAmountShipped(new Money(10.0, 'EUR'));

        $second = new LineItem('shipping', 1, new Money(5.0, 'EUR'), new Money(5.0, 'EUR'));
        $second->setShopwareLineItemId('delivery1');
        $second->setQuantityShipped(1);
        $second->setShippableQuantity(0);
        $second->setAmountShipped(new Money(5.0, 'EUR'));

        $mollieOrder = new Order('ord-1', '', null, [$first, $second]);

        $total = $this->builder->buildShippingTotal($mollieOrder, new OrderEntity(), true);

        self::assertSame('15.00', $total->amount);
        self::assertSame(2, $total->quantity);
        self::assertSame(1, $total->shippable);
    }

    public function testShippingTotalForOrdersApiIgnoresLinesThatWereNeverShipped(): void
    {
        $line = new LineItem('product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setShopwareLineItemId('sw1');
        $line->setShippableQuantity(1);

        $total = $this->builder->buildShippingTotal(new Order('ord-1', '', null, [$line]), new OrderEntity(), true);

        self::assertSame('0.00', $total->amount);
        self::assertSame(0, $total->quantity);
    }

    public function testShippingTotalSkipsContainerLineItems(): void
    {
        $container = $this->orderBuilder->createContainerLineItem('container1', 'Personalize this product', 10.0);
        $container->setCustomFields([Mollie::EXTENSION => ['quantity' => 1]]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$container]));

        $total = $this->builder->buildShippingTotal(null, $order, true);

        self::assertSame('0.00', $total->amount);
        self::assertSame(0, $total->quantity);
        self::assertSame(0, $total->shippable);
    }

    public function testShippingTotalHasNoAmountWithoutALoadedCurrency(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('li1', 'SW1', 2, 10.0, ['quantity' => 1]);
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        $total = $this->builder->buildShippingTotal(null, $order, true);

        self::assertSame('0.00', $total->amount);
        self::assertSame(1, $total->quantity);
    }

    private function mollieOrderWithRoundingLine(): Order
    {
        $line = new LineItem('rounding', 1, new Money(0.02, 'EUR'), new Money(0.02, 'EUR'));
        $line->setId('mollie-line-rounding');

        return new Order('ord-1', '', null, [$line]);
    }

    private function shopwareLineItem(string $id, int $quantity): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setQuantity($quantity);
        $lineItem->setCustomFields([]);

        return $lineItem;
    }
}
