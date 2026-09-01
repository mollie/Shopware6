<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Action;

use Mollie\Shopware\Component\FlowBuilder\Action\ShipOrderAction;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Shipment\AuthorizationReconciler;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShippingException;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Component\Shipment\ShipmentPersister;
use Mollie\Shopware\Component\Shipment\ShipmentTrackingResolver;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * The flow action ships the whole order, so a merchant can hang "ship at Mollie" on any flow. It
 * only unpacks the order id from the flow and hands it to the regular ship route.
 */
#[CoversClass(ShipOrderAction::class)]
final class ShipOrderActionTest extends TestCase
{
    private FakeOrderSearchRepository $orderRepository;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        $this->orderRepository = new FakeOrderSearchRepository();
        $this->gateway = new FakeGateway();
    }

    public function testTheActionIsOfferedUnderItsOwnName(): void
    {
        $this->assertSame('action.mollie.order.ship', ShipOrderAction::getName());
    }

    public function testTheActionRunsWhenTheFlowFiresIt(): void
    {
        $this->assertArrayHasKey(ShipOrderAction::getName(), ShipOrderAction::getSubscribedEvents());
    }

    /**
     * Without an order there is nothing to ship, so the Flow Builder must not offer the action on
     * a flow that carries none.
     */
    public function testTheActionIsOnlyOfferedOnFlowsThatCarryAnOrder(): void
    {
        $this->assertSame([OrderAware::class], (new ShipOrderAction($this->route(), new NullLogger()))->requirements());
    }

    public function testTheOrderFromTheFlowIsShippedAtMollie(): void
    {
        $order = $this->shippableOrder();

        $this->action()->handleFlow($this->flow($order->getId()));

        $this->assertCount(1, $this->gateway->getShipmentPayloads());
    }

    /**
     * Without items the route ships everything that is still open - a flow never picks single lines.
     */
    public function testTheWholeOrderIsShippedNotASingleLine(): void
    {
        $order = $this->shippableOrder();

        $this->action()->handleFlow($this->flow($order->getId()));

        $this->assertCount(1, $this->gateway->getShipmentPayloads()[0]->toArray()['lines']);
    }

    /**
     * The flow has to see the failure, or a merchant would believe the order was shipped.
     */
    public function testAFailedShipmentIsReportedBackToTheFlow(): void
    {
        $this->expectException(ShippingException::class);

        $this->action()->handleFlow($this->flow('an-order-that-does-not-exist'));
    }

    private function shippableOrder(): OrderEntity
    {
        $orderBuilder = new OrderEntityBuilder();

        $payment = new Payment('tr_1');
        $payment->setOrderId('ord_1');

        $lineItem = $orderBuilder->createShippableLineItem('lineitemid', 'SW100', 2, 10.0, ['order_line_id' => 'odl_1']);
        $order = $orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection([$lineItem]), $payment);
        $this->orderRepository->add($order);

        return $order;
    }

    private function flow(string $orderId): StorableFlow
    {
        return new StorableFlow(ShipOrderAction::getName(), Context::createDefaultContext(), ['orderId' => $orderId]);
    }

    private function action(): ShipOrderAction
    {
        return new ShipOrderAction($this->route(), new NullLogger());
    }

    private function route(): ShipOrderRoute
    {
        $logger = new NullLogger();
        $eventDispatcher = new EventSpy();

        $itemResolver = new ShipmentItemResolver(LineItemFilterBuilder::build());

        return new ShipOrderRoute(
            $this->orderRepository,
            $this->gateway,
            $eventDispatcher,
            $itemResolver,
            new ShipmentTrackingResolver(),
            new AuthorizationReconciler($this->gateway, $itemResolver, $logger),
            new ShipmentPersister(new FakeOrderRepository(), new FakeOrderRepository(), new FakeOrderService(), $eventDispatcher, $logger),
            new FakeTransactionService(),
            $logger,
        );
    }
}
