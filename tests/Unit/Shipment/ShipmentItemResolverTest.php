<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Shipment\Route\ShippingException;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(ShipmentItemResolver::class)]
final class ShipmentItemResolverTest extends TestCase
{
    private ShipmentItemResolver $resolver;

    private OrderEntityBuilder $orderBuilder;

    protected function setUp(): void
    {
        $lineItemFilter = LineItemFilterBuilder::build();
        $this->resolver = new ShipmentItemResolver($lineItemFilter);
        $this->orderBuilder = new OrderEntityBuilder();
    }

    public function testNormalizeItemsCastsAndSkipsNonArrays(): void
    {
        $normalized = $this->resolver->normalizeItems([
            ['id' => 'a', 'quantity' => '2'],
            'not-an-array',
            ['id' => 'b'],
        ]);

        self::assertSame([
            ['id' => 'a', 'quantity' => 2],
            ['id' => 'b', 'quantity' => 0],
        ], $normalized);
    }

    public function testNormalizeItemsReturnsEmptyArrayForNonArrayInput(): void
    {
        self::assertSame([], $this->resolver->normalizeItems('nope'));
    }

    public function testBuildRemainingItemsSubtractsShippedAndCancelled(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('id', 'SW1', 3, 10.0, ['quantity' => 1, 'cancelled_quantity' => 1]);
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        self::assertSame([['id' => 'id', 'quantity' => 1]], $this->resolver->buildRemainingItems($order));
    }

    public function testBuildRemainingItemsSkipsContainerLineItems(): void
    {
        $container = $this->orderBuilder->createContainerLineItem('containerid', 'Personalize this product', 10.0);
        $product = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW1', 1, 10.0);
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$container, $product]));

        self::assertSame([['id' => 'lineitemid', 'quantity' => 1]], $this->resolver->buildRemainingItems($order));
    }

    public function testCollectLineItemUpsertsSkipsContainerLineItems(): void
    {
        // The container duplicates the price of its child, so capturing both would exceed the
        // authorized amount at Mollie.
        $container = $this->orderBuilder->createContainerLineItem('containerid', 'Personalize this product', 10.0);
        $product = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 10.0);
        $lineItems = new OrderLineItemCollection([$container, $product]);
        $order = $this->orderBuilder->getOrderWithMolliePayment($lineItems);
        $currency = $order->getCurrency();
        self::assertNotNull($currency);

        $shippingItems = new ShippingItemCollection();

        $upserts = $this->resolver->collectLineItemUpserts(
            [['id' => 'containerid', 'quantity' => 1], ['id' => 'lineitemid', 'quantity' => 1]],
            $lineItems,
            $order->getId(),
            $shippingItems,
            $currency,
            (string) $order->getTaxStatus(),
        );

        self::assertCount(1, $upserts);
        self::assertSame('lineitemid', $upserts[0]['id']);
        self::assertCount(1, $shippingItems->all());
        self::assertSame(10.0, $shippingItems->getTotalAmount());
    }

    public function testIsFullyShippedIgnoresContainerLineItems(): void
    {
        $container = $this->orderBuilder->createContainerLineItem('containerid', 'Personalize this product', 10.0);
        $product = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW1', 1, 10.0);
        $lineItems = new OrderLineItemCollection([$container, $product]);

        $upserts = [['id' => 'lineitemid', 'customFields' => [Mollie::EXTENSION => ['quantity' => 1]]]];

        self::assertTrue($this->resolver->isFullyShipped($lineItems, $upserts));
    }

    public function testHasCancelledItems(): void
    {
        $cancelled = $this->orderBuilder->createShippableLineItem('id', 'SW1', 2, 10.0, ['cancelled_quantity' => 1]);
        $clean = $this->orderBuilder->createShippableLineItem('id', 'SW1', 2, 10.0);

        self::assertTrue($this->resolver->hasCancelledItems(new OrderLineItemCollection([$cancelled])));
        self::assertFalse($this->resolver->hasCancelledItems(new OrderLineItemCollection([$clean])));
    }

    public function testHasPriorShipments(): void
    {
        $shipped = $this->orderBuilder->createShippableLineItem('id', 'SW1', 2, 10.0, ['quantity' => 1]);
        $clean = $this->orderBuilder->createShippableLineItem('id', 'SW1', 2, 10.0);

        self::assertTrue($this->resolver->hasPriorShipments(new OrderLineItemCollection([$shipped])));
        self::assertFalse($this->resolver->hasPriorShipments(new OrderLineItemCollection([$clean])));
    }

    public function testIsFullyShippedConsidersUpsertQuantities(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW1', 2, 10.0);
        $lineItems = new OrderLineItemCollection([$lineItem]);

        $fullUpsert = [['id' => 'lineitemid', 'customFields' => [Mollie::EXTENSION => ['quantity' => 2]]]];
        $partialUpsert = [['id' => 'lineitemid', 'customFields' => [Mollie::EXTENSION => ['quantity' => 1]]]];

        self::assertTrue($this->resolver->isFullyShipped($lineItems, $fullUpsert));
        self::assertFalse($this->resolver->isFullyShipped($lineItems, $partialUpsert));
    }

    public function testCollectLineItemUpsertsBuildsUpsertAndShippingItem(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $lineItems = new OrderLineItemCollection([$lineItem]);
        $order = $this->orderBuilder->getOrderWithMolliePayment($lineItems);
        $currency = $order->getCurrency();
        self::assertNotNull($currency);

        $shippingItems = new ShippingItemCollection();

        $upserts = $this->resolver->collectLineItemUpserts(
            [['id' => 'lineitemid', 'quantity' => 1]],
            $lineItems,
            $order->getId(),
            $shippingItems,
            $currency,
            (string) $order->getTaxStatus(),
        );

        self::assertCount(1, $upserts);
        self::assertSame('lineitemid', $upserts[0]['id']);
        self::assertSame(1, $upserts[0]['customFields'][Mollie::EXTENSION]['quantity']);
        self::assertArrayNotHasKey('captureId', $upserts[0]['customFields'][Mollie::EXTENSION]);
        self::assertCount(1, $shippingItems->all());
        self::assertSame(10.0, $shippingItems->getTotalAmount());
    }

    public function testCollectLineItemUpsertsResolvesByProductNumber(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $lineItems = new OrderLineItemCollection([$lineItem]);
        $order = $this->orderBuilder->getOrderWithMolliePayment($lineItems);
        $currency = $order->getCurrency();
        self::assertNotNull($currency);

        $upserts = $this->resolver->collectLineItemUpserts(
            [['id' => 'SW100', 'quantity' => 1]],
            $lineItems,
            $order->getId(),
            new ShippingItemCollection(),
            $currency,
            (string) $order->getTaxStatus(),
        );

        self::assertSame('lineitemid', $upserts[0]['id']);
    }

    public function testCollectLineItemUpsertsThrowsWhenLineItemNotFound(): void
    {
        $lineItems = new OrderLineItemCollection();
        $currency = new CurrencyEntity();

        $this->expectException(ShippingException::class);

        try {
            $this->resolver->collectLineItemUpserts(
                [['id' => 'missing', 'quantity' => 1]],
                $lineItems,
                'order-id',
                new ShippingItemCollection(),
                $currency,
                '',
            );
        } catch (ShippingException $exception) {
            self::assertSame(ShippingException::LINE_ITEM_NOT_FOUND, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testCollectLineItemUpsertsThrowsWhenQuantityTooHigh(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $lineItems = new OrderLineItemCollection([$lineItem]);
        $order = $this->orderBuilder->getOrderWithMolliePayment($lineItems);
        $currency = $order->getCurrency();
        self::assertNotNull($currency);

        $this->expectException(ShippingException::class);

        try {
            $this->resolver->collectLineItemUpserts(
                [['id' => 'lineitemid', 'quantity' => 5]],
                $lineItems,
                $order->getId(),
                new ShippingItemCollection(),
                $currency,
                (string) $order->getTaxStatus(),
            );
        } catch (ShippingException $exception) {
            self::assertSame(ShippingException::SHIPPING_QUANTITY_TOO_HIGH, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testCollectLineItemUpsertsCarriesThePersistedMollieLineId(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $lineItems = new OrderLineItemCollection([$lineItem]);
        $shippingItems = new ShippingItemCollection();

        $this->resolver->collectLineItemUpserts(
            [['id' => 'lineitemid', 'quantity' => 1]],
            $lineItems,
            'order-id',
            $shippingItems,
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertSame('odl_123', $shippingItems->all()[0]->getMollieLineId());
    }

    public function testCollectDeliveryUpsertsMarksTheShippingCostsOfTheShippedItems(): void
    {
        $delivery = $this->orderBuilder->createShippableDelivery('deliveryid', 'lineitemid');
        $shippingItems = new ShippingItemCollection();

        $upserts = $this->resolver->collectDeliveryUpserts(
            [['id' => 'lineitemid', 'customFields' => []]],
            $shippingItems,
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertCount(1, $upserts);
        self::assertSame('deliveryid', $upserts[0]['id']);
        self::assertSame(1, $upserts[0]['customFields'][Mollie::EXTENSION]['quantity']);
        self::assertSame(4.99, $shippingItems->getTotalAmount());
    }

    public function testDeliveryOfAnotherShipmentIsNotMarkedAsShipped(): void
    {
        $delivery = $this->orderBuilder->createShippableDelivery('deliveryid', 'otherlineitemid');

        $upserts = $this->resolver->collectDeliveryUpserts(
            [['id' => 'lineitemid', 'customFields' => []]],
            new ShippingItemCollection(),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertCount(0, $upserts);
    }

    public function testAlreadyShippedDeliveryIsNotMarkedAgain(): void
    {
        $delivery = $this->orderBuilder->createShippableDelivery('deliveryid', 'lineitemid', 4.99, ['quantity' => 1]);

        $upserts = $this->resolver->collectDeliveryUpserts(
            [['id' => 'lineitemid', 'customFields' => []]],
            new ShippingItemCollection(),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertCount(0, $upserts);
    }

    public function testDeliveryWithoutPositionsIsSkipped(): void
    {
        $delivery = $this->orderBuilder->createDeliveryWithoutPositions('deliveryid');

        $upserts = $this->resolver->collectDeliveryUpserts(
            [['id' => 'lineitemid', 'customFields' => []]],
            new ShippingItemCollection(),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertCount(0, $upserts);
    }

    public function testDeliveryWithoutShippingMethodIsSkipped(): void
    {
        $delivery = $this->orderBuilder->createDeliveryWithoutShippingMethod('deliveryid', 'lineitemid');

        $upserts = $this->resolver->collectDeliveryUpserts(
            [['id' => 'lineitemid', 'customFields' => []]],
            new ShippingItemCollection(),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertCount(0, $upserts);
    }

    public function testShippedGrossSumsShippedItemsAndShippingCosts(): void
    {
        $shipped = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['quantity' => 2]);
        $delivery = $this->orderBuilder->createShippableDelivery('deliveryid', 'lineitemid', 5.0, ['quantity' => 1]);

        $total = $this->resolver->sumShippedGross(
            new OrderLineItemCollection([$shipped]),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertSame(25.0, $total);
    }

    public function testShippedGrossIgnoresItemsThatWereNeverShipped(): void
    {
        $unshipped = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $delivery = $this->orderBuilder->createShippableDelivery('deliveryid', 'lineitemid');

        $total = $this->resolver->sumShippedGross(
            new OrderLineItemCollection([$unshipped]),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertSame(0.0, $total);
    }

    public function testShippedGrossIgnoresContainerLineItems(): void
    {
        // A container duplicates the price of its children and was never sent to Mollie, so counting it
        // would report more as shipped than was ever authorized.
        $container = $this->orderBuilder->createContainerLineItem('containerlineitemid', 'Configured product', 25.0);
        $container->setCustomFields([Mollie::EXTENSION => ['quantity' => 1]]);

        $total = $this->resolver->sumShippedGross(
            new OrderLineItemCollection([$container]),
            new OrderDeliveryCollection(),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertSame(0.0, $total);
    }

    public function testShippedGrossIgnoresDeliveriesWithoutShippingMethod(): void
    {
        $delivery = $this->orderBuilder->createDeliveryWithoutShippingMethod('deliveryid', 'lineitemid', 4.99, ['quantity' => 1]);

        $total = $this->resolver->sumShippedGross(
            new OrderLineItemCollection(),
            new OrderDeliveryCollection([$delivery]),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
        );

        self::assertSame(0.0, $total);
    }

    public function testHasPriorShipmentsIgnoresContainerLineItems(): void
    {
        $container = $this->orderBuilder->createContainerLineItem('containerlineitemid', 'Configured product', 25.0);
        $container->setCustomFields([Mollie::EXTENSION => ['quantity' => 1]]);

        self::assertFalse($this->resolver->hasPriorShipments(new OrderLineItemCollection([$container])));
    }

    private function currency(): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        return $currency;
    }
}
