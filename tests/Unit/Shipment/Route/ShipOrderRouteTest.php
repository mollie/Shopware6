<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment\Route;

use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderResponse;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShippingException;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

class ShipOrderRouteTest extends TestCase
{
    private FakeOrderSearchRepository $orderRepository;

    private FakeOrderRepository $lineItemRepository;

    private FakeOrderRepository $deliveryRepository;

    private FakeGateway $gateway;

    private EventSpy $eventDispatcher;

    private OrderEntityBuilder $orderBuilder;

    private ShipOrderRoute $route;

    protected function setUp(): void
    {
        $this->orderRepository = new FakeOrderSearchRepository();
        $this->lineItemRepository = new FakeOrderRepository();
        $this->deliveryRepository = new FakeOrderRepository();
        $this->gateway = new FakeGateway();
        $this->eventDispatcher = new EventSpy();
        $this->orderBuilder = new OrderEntityBuilder();

        $orderService = new FakeOrderService();
        $logger = new NullLogger();

        $this->route = new ShipOrderRoute(
            $this->orderRepository,
            $this->lineItemRepository,
            $this->deliveryRepository,
            $this->gateway,
            $this->eventDispatcher,
            $orderService,
            $logger,
        );
    }

    public function testShipByOrderIdCapturesAndPersistsRequestedItems(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], [
            'orderId' => $order->getId(),
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
        ]);

        $response = $this->route->ship($request, Context::createDefaultContext());

        static::assertInstanceOf(ShipOrderResponse::class, $response);
        static::assertCount(1, $this->gateway->getCapturePayloads());
        static::assertSame(10.0, $this->gateway->getCapturePayloads()[0]->getAmount()->getValue());

        $upserts = $this->lineItemRepository->getUpserts();
        static::assertCount(1, $upserts);
        static::assertSame('lineitemid', $upserts[0]['id']);
        static::assertSame(1, $upserts[0]['customFields'][Mollie::EXTENSION]['quantity']);
        static::assertArrayHasKey('captureId', $upserts[0]['customFields'][Mollie::EXTENSION]);

        $event = $this->eventDispatcher->getEvent();
        static::assertInstanceOf(OrderShippedEvent::class, $event);
        static::assertCount(1, $event->getShippingItems()->all());
    }

    public function testShipByOrderNumberResolvesOrder(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 5.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], [
            'orderNumber' => $order->getOrderNumber(),
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
        ]);

        $this->route->ship($request, Context::createDefaultContext());

        static::assertCount(1, $this->gateway->getCapturePayloads());
    }

    public function testShipResolvesItemByProductNumber(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 5.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], [
            'orderId' => $order->getId(),
            'items' => [['id' => 'SW100', 'quantity' => 1]],
        ]);

        $this->route->ship($request, Context::createDefaultContext());

        $upserts = $this->lineItemRepository->getUpserts();
        static::assertCount(1, $upserts);
        static::assertSame('lineitemid', $upserts[0]['id']);
    }

    public function testShipWithoutItemsShipsEverythingStillOpen(): void
    {
        $first = $this->orderBuilder->createShippableLineItem('lineitemid1', 'SW100', 2, 10.0);
        $second = $this->orderBuilder->createShippableLineItem('lineitemid2', 'SW200', 3, 4.0, ['quantity' => 1]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$first, $second]));
        $this->orderRepository->add($order);

        $request = new Request([], ['orderId' => $order->getId()]);

        $this->route->ship($request, Context::createDefaultContext());

        $upserts = $this->lineItemRepository->getUpserts();
        static::assertCount(2, $upserts);

        $quantities = [];
        foreach ($upserts as $upsert) {
            $quantities[$upsert['id']] = $upsert['customFields'][Mollie::EXTENSION]['quantity'];
        }

        static::assertSame(2, $quantities['lineitemid1']);
        // already shipped 1 of 3, so the remaining 2 bring the shipped quantity to 3
        static::assertSame(3, $quantities['lineitemid2']);
    }

    public function testShipIsAnIdempotentNoopWhenNothingRemains(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['quantity' => 2]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], ['orderId' => $order->getId()]);

        $response = $this->route->ship($request, Context::createDefaultContext());

        static::assertInstanceOf(ShipOrderResponse::class, $response);
        static::assertSame('', $response->getObject()->get('mollieId'));
        static::assertCount(0, $this->gateway->getCapturePayloads());
        static::assertCount(0, $this->lineItemRepository->getUpserts());
        static::assertSame(0, $this->eventDispatcher->getEventCount());
    }

    public function testShipThrowsWhenOrderIsNotFound(): void
    {
        $request = new Request([], [
            'orderId' => 'missingorder',
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
        ]);

        $this->expectException(ShippingException::class);

        try {
            $this->route->ship($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::ORDER_NOT_FOUND, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testShipThrowsWhenOrderNumberIsNotFound(): void
    {
        $request = new Request([], [
            'orderNumber' => '99999',
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
        ]);

        $this->expectException(ShippingException::class);

        try {
            $this->route->ship($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::ORDER_NUMBER_NOT_FOUND, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testShipThrowsWhenLineItemIsAlreadyFullyShipped(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['quantity' => 2]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], [
            'orderId' => $order->getId(),
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
        ]);

        $this->expectException(ShippingException::class);

        try {
            $this->route->ship($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::LINE_ITEM_ALREADY_SHIPPED, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testShipThrowsWhenRequestedQuantityIsTooHigh(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], [
            'orderId' => $order->getId(),
            'items' => [['id' => 'lineitemid', 'quantity' => 5]],
        ]);

        $this->expectException(ShippingException::class);

        try {
            $this->route->ship($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::SHIPPING_QUANTITY_TOO_HIGH, $exception->getErrorCode());

            throw $exception;
        }
    }
}
