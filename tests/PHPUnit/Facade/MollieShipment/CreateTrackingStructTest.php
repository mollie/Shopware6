<?php

namespace MolliePayments\Tests\Facade\MollieShipment;

use InvalidArgumentException;
use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\Shipment;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\TrackingInfoStructFactory;
use Kiener\MolliePayments\Service\Transition\DeliveryTransitionService;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\Resources\Shipment as ShipmentResource;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class CreateTrackingStructTest extends TestCase
{
    private $shipmentFacade;

    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var OrderDeliveryEntity
     */
    private $delivery;

    /**
     * @var Shipment
     */
    private $shipmentApi;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderDataExtractor
     */
    private $orderDataExtractor;


    public function setUp(): void
    {
        $this->order = $this->createConfiguredMock(OrderEntity::class, [
            'getSalesChannelId' => 'foo'
        ]);

        $this->delivery = $this->createMock(OrderDeliveryEntity::class);

        $this->shipment = $this->createMock(ShipmentResource::class);

        $this->shipmentApi = $this->createConfiguredMock(Shipment::class, [
            'shipOrder' => $this->shipment
        ]);

        $this->orderService = $this->createConfiguredMock(OrderService::class, [
            'getMollieOrderId' => 'bar'
        ]);

        $this->orderDataExtractor = $this->createConfiguredMock(OrderDataExtractor::class, [
            'extractDelivery' => $this->delivery
        ]);


        $this->shipmentFacade = new MollieShipment(
            $this->createMock(MolliePaymentExtractor::class),
            $this->createMock(DeliveryTransitionService::class),
            $this->createMock(Order::class),
            $this->shipmentApi,
            $this->createMock(OrderDeliveryService::class),
            $this->orderService,
            $this->orderDataExtractor,
            new TrackingInfoStructFactory(),
            new NullLogger(),
        );

        $this->context = $this->createMock(Context::class);
    }

    public function testTrackingInfoStructWithEmptyTrackingDataReturnsNull()
    {
        $this->shipmentApi
            ->expects($this->once())
            ->method('shipOrder')
            ->willReturnCallback(function ($mollieOrderId, $salesChannelId, $trackingInfoStruct) {
                $this->assertNull($trackingInfoStruct);
            });

        $this->shipmentFacade->shipOrder($this->order, '', '', '', $this->context);
    }

    public function testTrackingInfoStructWithMissingTrackingCarrierThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->shipmentFacade->shipOrder($this->order, '', '123456789', '', $this->context);
    }

    public function testTrackingInfoStructWithMissingTrackingCodeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->shipmentFacade->shipOrder($this->order, 'Mollie', '', '', $this->context);
    }

    public function testTrackingInfoStructWithCorrectData()
    {
        $this->shipmentApi
            ->expects($this->once())
            ->method('shipOrder')
            ->willReturnCallback(function ($mollieOrderId, $salesChannelId, $trackingInfoStruct) {
                $this->assertInstanceOf(ShipmentTrackingInfoStruct::class, $trackingInfoStruct);
            });

        $this->shipmentFacade->shipOrder($this->order, 'Mollie', '123456789', 'https://foo.bar?code=%s', $this->context);
    }
}
