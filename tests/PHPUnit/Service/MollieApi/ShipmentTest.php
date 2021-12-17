<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\Shipment;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\Shipment as MollieShipment;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class ShipmentTest extends TestCase
{
    /**
     * @var Order
     */
    private $orderApiService;

    /**
     * @var Shipment
     */
    private $shipmentApiService;

    /**
     * @var MollieOrder
     */
    private $mollieOrder;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->mollieOrder = $this->createMock(MollieOrder::class);

        $this->orderApiService = $this->createMock(Order::class);
        $this->orderApiService->method('getMollieOrder')->willReturn($this->mollieOrder);

        $this->shipmentApiService = new Shipment($this->orderApiService);
    }

    /**
     * Tests if shipAll is being called
     */
    public function testShipOrder()
    {
        $this->mollieOrder
            ->expects($this->once())
            ->method('shipAll')
            ->willReturn($this->createMock(MollieShipment::class));

        $this->shipmentApiService->shipOrder('mollieOrderId', 'salesChannelId');
    }

    /**
     * Tests if correct exception is being thrown if order cannot be shipped
     */
    public function testShipOrderCannotBeShippedException()
    {
        $this->mollieOrder
            ->expects($this->once())
            ->method('shipAll')
            ->willThrowException(new ApiException());

        $this->expectException(MollieOrderCouldNotBeShippedException::class);

        $this->shipmentApiService->shipOrder('mollieOrderId', 'salesChannelId');
    }

    /**
     * Tests if createShipment is being called
     */
    public function testShipItem()
    {
        $this->mollieOrder
            ->expects($this->once())
            ->method('createShipment')
            ->willReturn($this->createMock(MollieShipment::class));

        $this->shipmentApiService->shipItem('mollieOrderId', 'salesChannelId', 'mollieOrderLineId', 1, $this->context);
    }

    /**
     * Tests if correct exception is being thrown if item cannot be shipped
     */
    public function testShipItemCannotBeShippedException()
    {
        $this->mollieOrder
            ->expects($this->once())
            ->method('createShipment')
            ->willThrowException(new ApiException());

        $this->expectException(MollieOrderCouldNotBeShippedException::class);

        $this->shipmentApiService->shipItem('mollieOrderId', 'salesChannelId', 'mollieOrderLineId', 1, $this->context);
    }
}
