<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Shipment;
use Mollie\Shopware\Component\Shipment\AuthorizationReconciler;
use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderResponse;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShippingException;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Component\Shipment\ShipmentPersister;
use Mollie\Shopware\Component\Shipment\ShipmentTrackingResolver;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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

    private ShipmentItemResolver $itemResolver;

    private ShipmentTrackingResolver $trackingResolver;

    private AuthorizationReconciler $reconciler;

    private ShipmentPersister $persister;

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

        $lineItemFilter = LineItemFilterBuilder::build();
        $itemResolver = new ShipmentItemResolver($lineItemFilter);
        $trackingResolver = new ShipmentTrackingResolver();
        $persister = new ShipmentPersister(
            $this->lineItemRepository,
            $this->deliveryRepository,
            $orderService,
            $this->eventDispatcher,
            $logger,
        );
        $reconciler = new AuthorizationReconciler($this->gateway, $itemResolver, $logger);

        $this->itemResolver = $itemResolver;
        $this->trackingResolver = $trackingResolver;
        $this->reconciler = $reconciler;
        $this->persister = $persister;

        $this->route = $this->buildRoute(new FakeTransactionService());
    }

    // ----------------------------------------------------------- Orders API path

    /**
     * The Orders API is line item based and captures on shipment, so an order that was created
     * through it gets a createShipment call instead of an explicit capture.
     */
    public function testAnOrdersApiOrderIsShippedInsteadOfCaptured(): void
    {
        $order = $this->ordersApiOrder();

        $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());

        static::assertCount(1, $this->gateway->getShipmentPayloads());
        static::assertCount(0, $this->gateway->getCapturePayloads());
    }

    public function testTheShipmentCarriesTheRequestedItems(): void
    {
        $order = $this->ordersApiOrder();

        $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());

        $payload = $this->gateway->getShipmentPayloads()[0]->toArray();
        static::assertCount(1, $payload['lines']);
        static::assertSame('odl_1', $payload['lines'][0]['id']);
    }

    /**
     * The tracking data from the request wins over anything derived from the deliveries, because
     * the merchant typed it in for this shipment.
     */
    public function testTrackingFromTheRequestIsSentToMollie(): void
    {
        $order = $this->ordersApiOrder();

        $request = new Request([], [
            'orderId' => $order->getId(),
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
            'trackingCarrier' => 'DHL',
            'trackingCode' => 'CODE-1',
            'trackingUrl' => 'https://dhl.test/CODE-1',
        ]);

        $this->route->ship($request, Context::createDefaultContext());

        $tracking = $this->gateway->getShipmentPayloads()[0]->toArray()['tracking'];
        static::assertSame('DHL', $tracking['carrier']);
        static::assertSame('CODE-1', $tracking['code']);
    }

    /**
     * Mollie answers with the shipment id. It has to land in the payment extension so an
     * accounting export finds it in the custom fields of the order.
     */
    public function testTheMollieShipmentIdIsRecordedOnThePaymentExtension(): void
    {
        $transactionService = new FakeTransactionService();
        $this->route = $this->buildRoute($transactionService);
        $this->gateway->withShipment(new Shipment('shp_1'));
        $order = $this->ordersApiOrder();

        $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());

        $saved = $transactionService->getSavedPaymentExtensions();
        static::assertSame(['shp_1'], $saved[0]['payment']->getShipmentIds());
    }

    public function testTheShipmentIdIsPersistedOnTheShippedLineItem(): void
    {
        $this->gateway->withShipment(new Shipment('shp_1'));
        $order = $this->ordersApiOrder();

        $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());

        $upserts = $this->lineItemRepository->getUpserts();
        static::assertSame('shp_1', $upserts[0]['customFields'][Mollie::EXTENSION]['shipmentId']);
    }

    /**
     * Shipping at Mollie may fail because the payment was already captured. That must not
     * interrupt the delivery state change, so nothing is persisted and the response stays empty.
     */
    public function testAFailingShipmentLeavesTheDeliveryStateAlone(): void
    {
        $this->gateway->withCreateShipmentThrowing();
        $order = $this->ordersApiOrder();

        $response = $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());

        static::assertSame('', $response->getMollieId());
        static::assertCount(0, $this->lineItemRepository->getUpserts());
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

    public function testShipDoesNotCaptureContainerLineItems(): void
    {
        // Container line items (e.g. SwagCustomizedProducts) duplicate the price of their children and
        // are never sent to Mollie. Capturing them too exceeded the authorized amount and made Mollie
        // reject the capture with "The amount to capture is higher than the remaining authorized amount".
        $container = $this->orderBuilder->createContainerLineItem('containerid', 'Personalize this product', 10.0);
        $product = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$container, $product]));
        $this->orderRepository->add($order);

        $request = new Request([], ['orderId' => $order->getId()]);

        $this->route->ship($request, Context::createDefaultContext());

        static::assertCount(1, $this->gateway->getCapturePayloads());
        static::assertSame(10.0, $this->gateway->getCapturePayloads()[0]->getAmount()->getValue());

        $upserts = $this->lineItemRepository->getUpserts();
        static::assertCount(1, $upserts);
        static::assertSame('lineitemid', $upserts[0]['id']);
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
        // The transaction repair event is dispatched (needed to resolve the payment for reconciliation),
        // but nothing is shipped, so no OrderShippedEvent follows.
        foreach ($this->eventDispatcher->getEvents() as $event) {
            static::assertNotInstanceOf(OrderShippedEvent::class, $event);
        }
    }

    public function testShipShipsEvenWhenPaymentIsNotAuthorized(): void
    {
        // Merchants may set an authorized order to paid themselves (for their ERP); those orders must
        // still be shipped instead of being treated as a no-op.
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 10.0);
        $order = $this->orderBuilder->getOrderWithNonCapturablePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], ['orderId' => $order->getId()]);

        $response = $this->route->ship($request, Context::createDefaultContext());

        static::assertInstanceOf(ShipOrderResponse::class, $response);
        static::assertCount(1, $this->gateway->getCapturePayloads());
        static::assertCount(1, $this->lineItemRepository->getUpserts());

        $event = $this->eventDispatcher->getEvent();
        static::assertInstanceOf(OrderShippedEvent::class, $event);
    }

    public function testShipSwallowsMollieApiErrorInsteadOfInterruptingStateChange(): void
    {
        // A failing Mollie shipment (e.g. the payment was already captured) must not interrupt the
        // delivery state change: the error is logged and nothing is persisted or dispatched.
        $this->gateway->withCaptureThrowing();

        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], ['orderId' => $order->getId()]);

        $response = $this->route->ship($request, Context::createDefaultContext());

        static::assertInstanceOf(ShipOrderResponse::class, $response);
        static::assertSame('', $response->getObject()->get('mollieId'));
        static::assertCount(0, $this->lineItemRepository->getUpserts());

        foreach ($this->eventDispatcher->getEvents() as $event) {
            static::assertNotInstanceOf(OrderShippedEvent::class, $event);
        }
    }

    public function testShipIsANoopWhenTransactionHasNoMolliePayment(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 1, 10.0);
        $order = $this->orderBuilder->getOrderWithoutMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->orderRepository->add($order);

        $request = new Request([], ['orderId' => $order->getId()]);

        $response = $this->route->ship($request, Context::createDefaultContext());

        static::assertInstanceOf(ShipOrderResponse::class, $response);
        static::assertCount(0, $this->gateway->getCapturePayloads());
        static::assertCount(0, $this->lineItemRepository->getUpserts());
        // The repair event is dispatched, but no OrderShippedEvent follows since no payment was found.
        foreach ($this->eventDispatcher->getEvents() as $event) {
            static::assertNotInstanceOf(OrderShippedEvent::class, $event);
        }
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

    /**
     * The route is the extension point other plugins decorate; it is the innermost one itself.
     */
    public function testTheRouteIsTheInnermostOneAndHasNothingToDelegateTo(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->route->getDecorated();
    }

    /**
     * A guest order that was paid outside Shopware can end up without a transaction; there is no
     * Mollie payment to ship then.
     */
    public function testAnOrderWithoutAnyTransactionIsNotShipped(): void
    {
        $order = $this->ordersApiOrder();
        $order->setTransactions(new OrderTransactionCollection());

        $this->expectException(ShippingException::class);

        $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());
    }

    /**
     * The Mollie line ids are only needed to fill in what the order does not know. An unreachable
     * Mollie must not stop the shipment - the delivery state change still has to happen.
     */
    public function testAnUnreachableMollieOrderDoesNotStopTheShipment(): void
    {
        $order = $this->ordersApiOrder();
        $this->gateway->withGetOrderException();

        $this->route->ship($this->shipRequest($order->getId()), Context::createDefaultContext());

        static::assertCount(1, $this->gateway->getShipmentPayloads());
    }

    private function buildRoute(FakeTransactionService $transactionService): ShipOrderRoute
    {
        return new ShipOrderRoute(
            $this->orderRepository,
            $this->gateway,
            $this->eventDispatcher,
            $this->itemResolver,
            $this->trackingResolver,
            $this->reconciler,
            $this->persister,
            $transactionService,
            new NullLogger(),
        );
    }

    // ----------------------------------------------------------------- helpers

    private function ordersApiOrder(): OrderEntity
    {
        $payment = new Payment('tr_1');
        $payment->setOrderId('ord_1');

        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, [
            'order_line_id' => 'odl_1',
        ]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $payment);
        $this->orderRepository->add($order);

        return $order;
    }

    private function shipRequest(string $orderId): Request
    {
        return new Request([], [
            'orderId' => $orderId,
            'items' => [['id' => 'lineitemid', 'quantity' => 1]],
        ]);
    }
}
