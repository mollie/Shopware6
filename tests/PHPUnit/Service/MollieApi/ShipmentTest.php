<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\Shipment;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\OrderLineCollection;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Types\OrderLineType;
use PHPUnit\Framework\TestCase;

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

    protected function setUp(): void
    {
        $this->mollieOrder = $this->createMock(MollieOrder::class);

        $this->orderApiService = $this->createMock(Order::class);
        $this->orderApiService->method('getMollieOrder')->willReturn($this->mollieOrder);

        $this->shipmentApiService = new Shipment($this->orderApiService);
    }

    protected function setUpOrderLines()
    {
        $this->mollieOrder->method('lines')->willReturnCallback(function () {
            $collection = new OrderLineCollection(4, \Safe\json_decode('{}'));

            // Total shipped: 5 items, amount € 175.00

            // Physical, ID: foo, 2 shipped, 3 shippable, 5 total. Amount shipped € 100.
            $line1 = new OrderLine(new MollieApiClient());
            $line1->id = 'odl_1';
            $line1->type = OrderLineType::TYPE_PHYSICAL;
            $line1->metadata = \Safe\json_decode(\Safe\json_encode(['orderLineItemId' => 'foo']));
            $line1->quantity = 5;
            $line1->quantityShipped = 2;
            $line1->shippableQuantity = 3;
            $line1->amountShipped = \Safe\json_decode(\Safe\json_encode(['value' => 100.00]));
            $collection->append($line1);

            // Digital, ID: bar, 1 shipped, 1 shippable, 2 total. Amount shipped € 25.
            $line2 = new OrderLine(new MollieApiClient());
            $line2->id = 'odl_2';
            $line2->type = OrderLineType::TYPE_DIGITAL;
            $line2->metadata = \Safe\json_decode(\Safe\json_encode(['orderLineItemId' => 'bar']));
            $line2->quantity = 2;
            $line2->quantityShipped = 1;
            $line2->shippableQuantity = 1;
            $line2->amountShipped = \Safe\json_decode(\Safe\json_encode(['value' => 25.00]));
            $collection->append($line2);

            // Shipping Fee, ID: baz, 0 shipped, 1 shippable, 1 total. Amount shipped € 100.
            $line3 = new OrderLine(new MollieApiClient());
            $line3->id = 'odl_3';
            $line3->type = OrderLineType::TYPE_SHIPPING_FEE;
            $line3->metadata = \Safe\json_decode(\Safe\json_encode(['orderLineItemId' => 'baz']));
            $line3->quantity = 1;
            $line3->quantityShipped = 0;
            $line3->shippableQuantity = 1;
            $line3->amountShipped = \Safe\json_decode(\Safe\json_encode(['value' => 6.95]));
            $collection->append($line3);

            // Discount, ID: buzz, 2 shipped, 0 shippable, 2 total. Amount shipped € 100.
            $line4 = new OrderLine(new MollieApiClient());
            $line4->id = 'odl_4';
            $line4->type = OrderLineType::TYPE_DISCOUNT;
            $line4->metadata = \Safe\json_decode(\Safe\json_encode(['orderLineItemId' => 'bax']));
            $line4->quantity = 2;
            $line4->quantityShipped = 2;
            $line4->shippableQuantity = 0;
            $line4->amountShipped = \Safe\json_decode(\Safe\json_encode(['value' => 50.00]));
            $collection->append($line4);

            return $collection;
        });
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

        $this->shipmentApiService->shipOrder('mollieOrderId', 'salesChannelId', [], null);
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

        $this->shipmentApiService->shipOrder('mollieOrderId', 'salesChannelId', [], null);
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

        $this->shipmentApiService->shipItem('mollieOrderId', 'salesChannelId', 'mollieOrderLineId', 1, null);
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

        $this->shipmentApiService->shipItem('mollieOrderId', 'salesChannelId', 'mollieOrderLineId', 1, null);
    }

    /**
     * Test for getting the status of the mollie order lines. shipping fee order line is excluded
     *
     * @return void
     */
    public function testGetStatus()
    {
        $this->setUpOrderLines();

        $expectedStatus = [
            'foo' => [
                'id' => 'foo',
                'mollieOrderLineId' => 'odl_1',
                'quantity' => 5,
                'quantityShippable' => 3,
                'quantityShipped' => 2,
            ],
            'bar' => [
                'id' => 'bar',
                'mollieOrderLineId' => 'odl_2',
                'quantity' => 2,
                'quantityShippable' => 1,
                'quantityShipped' => 1,
            ],
            'bax' => [
                'id' => 'bax',
                'mollieOrderLineId' => 'odl_4',
                'quantity' => 2,
                'quantityShippable' => 0,
                'quantityShipped' => 2,
            ],
        ];

        $actualStatus = $this->shipmentApiService->getStatus('mollieOrderId', 'salesChannelId');
        $this->assertSame($expectedStatus, $actualStatus);
    }

    public function testGetTotals()
    {
        $this->setUpOrderLines();

        $expectedTotals = [
            'amount' => 175.0,
            'quantity' => 5,
            'shippableQuantity' => 4
        ];

        $actualTotals = $this->shipmentApiService->getTotals('mollieOrderId', 'salesChannelId');
        $this->assertSame($expectedTotals, $actualTotals);
    }
}
