<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Shipment\CancelItemEvent;
use Mollie\Shopware\Component\Shipment\Route\CancelItemRoute;
use Mollie\Shopware\Component\Transaction\Event\RepairLegacyTransactionEvent;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeOrderLineItemRepository;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Refund\Fake\FakeStockStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CancelItemRoute::class)]
final class CancelItemRouteTest extends TestCase
{
    private FakeOrderLineItemRepository $lineItemRepository;

    private FakeGateway $gateway;

    private FakeStockStorage $stockStorage;

    private EventSpy $eventDispatcher;

    private OrderEntityBuilder $orderBuilder;

    private CancelItemRoute $route;

    protected function setUp(): void
    {
        $this->lineItemRepository = new FakeOrderLineItemRepository();
        $this->gateway = new FakeGateway();
        $this->stockStorage = new FakeStockStorage();
        $this->eventDispatcher = new EventSpy();
        $this->orderBuilder = new OrderEntityBuilder();

        $this->route = new CancelItemRoute(
            $this->lineItemRepository,
            $this->gateway,
            $this->stockStorage,
            LineItemFilterBuilder::build(),
            $this->eventDispatcher,
        );
    }

    public function testCancelFailsWithoutShopwareLineId(): void
    {
        $request = new Request([], ['quantity' => 1]);

        $response = $this->route->cancel($request, Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Missing shopwareLineId', $this->decode($response)['message']);
    }

    public function testCancelFailsWhenQuantityIsZero(): void
    {
        $request = new Request([], ['shopwareLineId' => 'lineitemid', 'quantity' => 0]);

        $response = $this->route->cancel($request, Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('quantityZero', $this->decode($response)['message']);
    }

    public function testCancelFailsWhenLineItemDoesNotExist(): void
    {
        $request = new Request([], ['shopwareLineId' => 'unknownlineitemid', 'quantity' => 1]);

        $response = $this->route->cancel($request, Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalidShopwareLineId', $this->decode($response)['message']);
    }

    public function testCancelFailsWhenLineItemHasNoOrder(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $this->lineItemRepository->add($lineItem);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalidShopwareLineId', $this->decode($response)['message']);
    }

    public function testCancelFailsWhenPaymentIsAlreadyPaid(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $order = $this->orderBuilder->getOrderWithNonCapturablePayment(new OrderLineItemCollection([$lineItem]));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('notAuthorized', $this->decode($response)['message']);
    }

    public function testCancelFailsWhenTransactionCarriesNoMolliePayment(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $order = $this->orderBuilder->getOrderWithoutMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('notMollieOrder', $this->decode($response)['message']);
    }

    public function testLegacyTransactionIsRepairedBeforeThePaymentIsRead(): void
    {
        // Legacy orders only carry the Mollie data on the order; a subscriber copies it onto the
        // transaction, so the repair has to run before the extension is read and the item cancelled.
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        $events = $this->eventDispatcher->getEvents();
        self::assertInstanceOf(RepairLegacyTransactionEvent::class, $events[0]);
        self::assertSame('fake-transaction-id', $events[0]->getTransaction()->getId());
        self::assertSame($order, $events[0]->getOrder());
        self::assertInstanceOf(CancelItemEvent::class, $events[1]);
    }

    public function testCancelViaOrdersApiCancelsTheMollieOrderLine(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 2), Context::createDefaultContext());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([[
            'mollieOrderId' => 'ord_123',
            'mollieLineId' => 'odl_123',
            'quantity' => 2,
            'orderNumber' => '10000',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]], $this->gateway->getCancelledOrderLines());
    }

    public function testCancelViaOrdersApiReturnsTheCancelledMollieLine(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame([
            'success' => true,
            'message' => '',
            'data' => ['id' => 'odl_123', 'quantity' => 1],
        ], $this->decode($response));
    }

    public function testCancelViaOrdersApiFailsWithoutMollieLineId(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalidLine', $this->decode($response)['message']);
        self::assertSame([], $this->gateway->getCancelledOrderLines());
    }

    public function testCancelledItemEventIsDispatchedForTheCurrentTransaction(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        $event = $this->eventDispatcher->getEvent();
        self::assertInstanceOf(CancelItemEvent::class, $event);
        self::assertSame('fake-transaction-id', $event->getTransactionId());
    }

    public function testStockIsReturnedWhenRequested(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $lineItem->setReferencedId('productid');
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 2, true), Context::createDefaultContext());

        self::assertCount(1, $this->stockStorage->alterations);
        self::assertSame('productid', $this->stockStorage->alterations[0]->productId);
        self::assertSame(2, $this->stockStorage->alterations[0]->quantityBefore);
        self::assertSame(0, $this->stockStorage->alterations[0]->newQuantity);
    }

    public function testStockIsKeptWhenNotRequested(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $lineItem->setReferencedId('productid');
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 2), Context::createDefaultContext());

        self::assertCount(0, $this->stockStorage->alterations);
    }

    public function testStockIsKeptForALineItemWithoutProduct(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_123']);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $this->paymentWithOrderId('ord_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 2, true), Context::createDefaultContext());

        self::assertCount(0, $this->stockStorage->alterations);
    }

    public function testCancelViaPaymentsApiRecordsTheCancelledQuantity(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 3, 10.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'id' => 'lineitemid',
            'customFields' => [Mollie::EXTENSION => ['cancelled_quantity' => 1]],
        ], $this->lineItemRepository->getLastUpsert());
    }

    public function testCancelViaPaymentsApiAddsToAnAlreadyCancelledQuantity(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 3, 10.0, ['quantity' => 1, 'cancelled_quantity' => 1]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame([
            'id' => 'lineitemid',
            'customFields' => [Mollie::EXTENSION => ['quantity' => 1, 'cancelled_quantity' => 2]],
        ], $this->lineItemRepository->getLastUpsert());
    }

    public function testCancelViaPaymentsApiFailsWhenMoreThanTheOpenQuantityIsCancelled(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 3, 10.0, ['quantity' => 1, 'cancelled_quantity' => 1]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]));
        $this->register($lineItem, $order);

        $response = $this->route->cancel($this->cancelRequest('lineitemid', 2), Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('quantityTooHigh', $this->decode($response)['message']);
        self::assertSame([], $this->lineItemRepository->getUpserts());
    }

    public function testAuthorizationIsReleasedWhenEveryItemIsShippedOrCancelled(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['quantity' => 1]);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), new Payment('tr_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 1), Context::createDefaultContext());

        self::assertSame([[
            'paymentId' => 'tr_123',
            'orderNumber' => '10000',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ]], $this->gateway->getReleasedAuthorizations());
    }

    public function testAuthorizationIsKeptWhileAnotherItemIsStillOpen(): void
    {
        $cancelled = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $open = $this->orderBuilder->createShippableLineItem('otherlineitemid', 'SW200', 1, 5.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$cancelled, $open]));
        $this->register($cancelled, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 2), Context::createDefaultContext());

        self::assertSame([], $this->gateway->getReleasedAuthorizations());
    }

    public function testAuthorizationIsReleasedWhileAContainerLineItemHasNoQuantityOfItsOwn(): void
    {
        $lineItem = $this->orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0);
        $container = $this->orderBuilder->createContainerLineItem('containerlineitemid', 'Configured product', 25.0);
        $order = $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem, $container]), new Payment('tr_123'));
        $this->register($lineItem, $order);

        $this->route->cancel($this->cancelRequest('lineitemid', 2), Context::createDefaultContext());

        self::assertCount(1, $this->gateway->getReleasedAuthorizations());
    }

    private function paymentWithOrderId(string $mollieOrderId): Payment
    {
        $payment = new Payment('tr_123');
        $payment->setOrderId($mollieOrderId);

        return $payment;
    }

    private function register(OrderLineItemEntity $lineItem, OrderEntity $order): void
    {
        $lineItem->setOrder($order);
        $this->lineItemRepository->add($lineItem);
    }

    private function cancelRequest(string $shopwareLineId, int $quantity, bool $resetStock = false): Request
    {
        return new Request([], [
            'shopwareLineId' => $shopwareLineId,
            'quantity' => $quantity,
            'resetStock' => $resetStock,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
