<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\ShipmentManager;

use Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoDeliveriesFoundException;
use Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoLineItemsProvidedException;
use Kiener\MolliePayments\Components\ShipmentManager\Models\ShipmentLineItem;
use Kiener\MolliePayments\Components\ShipmentManager\Models\TrackingData;
use Kiener\MolliePayments\Components\ShipmentManager\ShipmentManager;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDeliveryExtractor;
use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\TrackingInfoStructFactory;
use Kiener\MolliePayments\Service\Transition\DeliveryTransitionService;
use Kiener\MolliePayments\Service\UrlParsingService;
use MolliePayments\Shopware\Tests\Fakes\FakeShipment;
use MolliePayments\Shopware\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Context;

class ShipmentManagerTest extends TestCase
{
    use OrderTrait;

    /**
     * @var ShipmentManager
     */
    private $shipmentManager;

    /**
     * @var FakeShipment
     */
    private $fakeShipmentService;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        $this->fakeShipmentService = new FakeShipment();
        $logger = new NullLogger();
        $deliveryTransitionService = $this->createMock(DeliveryTransitionService::class);
        $mollieApiOrderService = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $orderService = $this->getMockBuilder(OrderService::class)->disableOriginalConstructor()->getMock();
        $deliveryExtractor = new OrderDeliveryExtractor($logger);

        $this->shipmentManager = new ShipmentManager(
            $deliveryTransitionService,
            $mollieApiOrderService,
            $this->fakeShipmentService,
            $orderService,
            $deliveryExtractor,
            new OrderItemsExtractor(),
            new TrackingInfoStructFactory(new UrlParsingService(), $logger),
            $logger
        );

        $this->context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * This test verifies that our shipOrderRest works correctly.
     * This is defined by passing an empty line item array to our shipment service.
     * We also do not provide any tracking information. In this case, the tracking data will be
     * read from the order, which is also empty in this test.
     *
     * @throws \Exception
     */
    public function testShipOrderRestWithoutTracking(): void
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');

        $this->shipmentManager->shipOrderRest($order, null, $this->context);

        $this->assertTrue($this->fakeShipmentService->isShipOrderCalled());
        // make sure that the correct order ID is passed on
        $this->assertEquals('ord_123', $this->fakeShipmentService->getShippedMollieOrderId());
        // no items should be passed on to do a "shipAll" call
        $this->assertCount(0, $this->fakeShipmentService->getShippedItems());
        // no tracking data should be passed on
        $this->assertNull($this->fakeShipmentService->getShippedTracking());
    }

    /**
     * This test verifies that our shipOrderRest works correctly.
     * This is defined by passing an empty line item array to our shipment service.
     * We also do not provide any tracking information. But our order as tracking information,
     * so it should be passed on correctly to Mollie.
     *
     * @throws \Exception
     */
    public function testShipOrderRestWithTrackingFromDelivery(): void
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $order->getDeliveries()->first();
        $delivery->setTrackingCodes(['code-123']);

        $this->shipmentManager->shipOrderRest($order, null, $this->context);

        $this->assertTrue($this->fakeShipmentService->isShipOrderCalled());
        // make sure that the correct order ID is passed on
        $this->assertEquals('ord_123', $this->fakeShipmentService->getShippedMollieOrderId());
        // no items should be passed on to do a "shipAll" call
        $this->assertCount(0, $this->fakeShipmentService->getShippedItems());
        // delivery tracking data should be passed on
        $this->assertEquals('code-123', $this->fakeShipmentService->getShippedTracking()->getCode());
    }

    /**
     * This test verifies that our shipOrderRest works correctly.
     * This is defined by passing an empty line item array to our shipment service.
     * We also provide custom tracking data that needs to be used.
     *
     * @throws \Exception
     */
    public function testShipOrderRestWithCustomTracking(): void
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');

        $trackingData = new TrackingData(
            'DHL Standard',
            'code-abc',
            'https://www.mollie.com?code=%s'
        );

        $this->shipmentManager->shipOrderRest($order, $trackingData, $this->context);

        $this->assertTrue($this->fakeShipmentService->isShipOrderCalled());
        // make sure that the correct order ID is passed on
        $this->assertEquals('ord_123', $this->fakeShipmentService->getShippedMollieOrderId());
        // no items should be passed on to do a "shipAll" call
        $this->assertCount(0, $this->fakeShipmentService->getShippedItems());
        // custom tracking data should be passed on
        $this->assertEquals('code-abc', $this->fakeShipmentService->getShippedTracking()->getCode());
        $this->assertEquals('DHL Standard', $this->fakeShipmentService->getShippedTracking()->getCarrier());
        $this->assertEquals('https://www.mollie.com?code=code-abc', $this->fakeShipmentService->getShippedTracking()->getUrl());
    }

    /**
     * This test verifies that we get a successful exception
     * if our order in Shopware somehow has no deliveries.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipOrderRestFailsWithoutDeliveries()
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1]));

        // overwrite deliveries
        $order->setDeliveries(new OrderDeliveryCollection([]));

        $this->expectException(NoDeliveriesFoundException::class);

        $this->shipmentManager->shipOrderRest(
            $order,
            null,
            $this->context
        );

        // make sure we don't call the Mollie API
        $this->assertFalse($this->fakeShipmentService->isShipOrderCalled());
    }

    /**
     * This test verifies that our shipOrder throws a valid exception
     * if no line items have been provided.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipOrderWithoutTrackingNoLineItems()
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');

        $this->expectException(NoLineItemsProvidedException::class);

        $this->shipmentManager->shipOrder($order, null, [], $this->context);

        // make sure we don't call the Mollie API
        $this->assertFalse($this->fakeShipmentService->isShipOrderCalled());
    }

    /**
     * This test verifies that our shipOrder work correctly.
     * We need to provide a line item for this.
     * In this test case we do not have any tracking information, neither in the
     * custom request, nor in the order delivery itself, so nothing should be tracked.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipOrderWithoutTracking()
    {
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $lineItem2 = $this->buildLineItemEntity('SKU-2');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));

        $this->shipmentManager->shipOrder(
            $order,
            null,
            [
                new ShipmentLineItem($lineItem1->getId(), 1),
            ],
            $this->context
        );

        $this->assertTrue($this->fakeShipmentService->isShipOrderCalled());
        // make sure that the correct order ID is passed on
        $this->assertEquals('ord_123', $this->fakeShipmentService->getShippedMollieOrderId());
        // 1 line item should be passed
        $this->assertCount(1, $this->fakeShipmentService->getShippedItems());
        // no tracking is sent
        $this->assertNull($this->fakeShipmentService->getShippedTracking());
    }

    /**
     * This test verifies that our shipOrder work correctly.
     * We need to provide a line item for this.
     * In this test case we do provide any tracking information,
     * but the order already has one, so it should be used.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipOrderWithTrackingFromDelivery()
    {
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $lineItem2 = $this->buildLineItemEntity('SKU-2');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $order->getDeliveries()->first();
        $delivery->setTrackingCodes(['code-123']);

        $this->shipmentManager->shipOrder(
            $order,
            null,
            [
                new ShipmentLineItem($lineItem1->getId(), 1),
            ],
            $this->context
        );

        $this->assertTrue($this->fakeShipmentService->isShipOrderCalled());
        // make sure that the correct order ID is passed on
        $this->assertEquals('ord_123', $this->fakeShipmentService->getShippedMollieOrderId());
        // 1 line item should be passed
        $this->assertCount(1, $this->fakeShipmentService->getShippedItems());
        // delivery tracking data should be passed on
        $this->assertEquals('code-123', $this->fakeShipmentService->getShippedTracking()->getCode());
    }

    /**
     * This test verifies that our shipOrder work correctly.
     * We need to provide a line item for this.
     * In this test case we provide custom tracking information,
     * which should be used.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipOrderWithCustomTracking()
    {
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $lineItem2 = $this->buildLineItemEntity('SKU-2');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));

        $trackingData = new TrackingData(
            'DHL Standard',
            'code-abc',
            'https://www.mollie.com?code=%s'
        );

        $this->shipmentManager->shipOrder(
            $order,
            $trackingData,
            [
                new ShipmentLineItem($lineItem1->getId(), 1),
            ],
            $this->context
        );

        $this->assertTrue($this->fakeShipmentService->isShipOrderCalled());
        // make sure that the correct order ID is passed on
        $this->assertEquals('ord_123', $this->fakeShipmentService->getShippedMollieOrderId());
        // 1 line item should be passed
        $this->assertCount(1, $this->fakeShipmentService->getShippedItems());
        // custom tracking data should be passed on
        $this->assertEquals('code-abc', $this->fakeShipmentService->getShippedTracking()->getCode());
        $this->assertEquals('DHL Standard', $this->fakeShipmentService->getShippedTracking()->getCarrier());
        $this->assertEquals('https://www.mollie.com?code=code-abc', $this->fakeShipmentService->getShippedTracking()->getUrl());
    }

    /**
     * This test verifies that we get a successful exception
     * if our order in Shopware somehow has no deliveries.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipOrderFailsWithoutDeliveries()
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1]));

        // overwrite deliveries
        $order->setDeliveries(new OrderDeliveryCollection([]));

        $this->expectException(NoDeliveriesFoundException::class);

        $this->shipmentManager->shipOrder(
            $order,
            null,
            [
                new ShipmentLineItem($lineItem1->getId(), 1),
            ],
            $this->context
        );

        // make sure we don't call the Mollie API
        $this->assertFalse($this->fakeShipmentService->isShipOrderCalled());
    }

    /**
     * This test verifies if a specific item shipment is correctly being passed on.
     * We do not provide any tracking information, neither in the custom request, nor in the order delivery itself.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipItemWithoutTracking()
    {
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $lineItem2 = $this->buildLineItemEntity('SKU-2');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));

        $this->shipmentManager->shipItem(
            $order,
            'SKU-1',
            2,
            null,
            $this->context
        );

        // make sure that the correct order ID is passed on
        $this->assertTrue($this->fakeShipmentService->isShipItemCalled());
        // 1 line item should be passed
        $this->assertCount(1, $this->fakeShipmentService->getShippedItems());
        $this->assertEquals(2, $this->fakeShipmentService->getShippedItemQty());
        // no tracking is sent
        $this->assertNull($this->fakeShipmentService->getShippedTracking());
    }

    /**
     * This test verifies if a specific item shipment is correctly being passed on.
     * We do not provide custom tracking data, but our order delivery has data which should be used.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipItemWithTrackingFromDelivery()
    {
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $lineItem2 = $this->buildLineItemEntity('SKU-2');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $order->getDeliveries()->first();
        $delivery->setTrackingCodes(['code-123']);

        $this->shipmentManager->shipItem(
            $order,
            'SKU-1',
            2,
            null,
            $this->context
        );

        // make sure that the correct order ID is passed on
        $this->assertTrue($this->fakeShipmentService->isShipItemCalled());
        // delivery tracking data should be passed on
        $this->assertEquals('code-123', $this->fakeShipmentService->getShippedTracking()->getCode());
    }

    /**
     * This test verifies if a specific item shipment is correctly being passed on.
     * We do provide custom tracking data that should be used
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipItemWithCustomTracking()
    {
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $lineItem2 = $this->buildLineItemEntity('SKU-2');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));

        $this->shipmentManager->shipItem(
            $order,
            'SKU-1',
            2,
            new TrackingData(
                'DHL Standard',
                'code-abc',
                'https://www.mollie.com?code=%s'
            ),
            $this->context
        );

        // make sure that the correct order ID is passed on
        $this->assertTrue($this->fakeShipmentService->isShipItemCalled());
        // delivery tracking data should be passed on
        $this->assertEquals('code-abc', $this->fakeShipmentService->getShippedTracking()->getCode());
    }

    /**
     * This test verifies that we get a successful exception
     * if our order in Shopware somehow has no deliveries.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShipItemFailsWithoutDeliveries()
    {
        // we build an order without a delivery that contains tracking information
        $order = $this->buildMollieOrder('ord_123');
        $lineItem1 = $this->buildLineItemEntity('SKU-1');
        $order->setLineItems(new OrderLineItemCollection([$lineItem1]));

        // overwrite deliveries
        $order->setDeliveries(new OrderDeliveryCollection([]));

        $this->expectException(NoDeliveriesFoundException::class);

        $this->shipmentManager->shipItem(
            $order,
            'SKU-1',
            2,
            null,
            $this->context
        );

        // make sure we don't call the Mollie API
        $this->assertFalse($this->fakeShipmentService->isShipOrderCalled());
    }
}
