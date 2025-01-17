<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Components\ShipmentManager\Models\TrackingData;
use Kiener\MolliePayments\Components\ShipmentManager\ShipmentManagerInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Shipment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class FakeShipmentManager implements ShipmentManagerInterface
{
    /**
     * @var false
     */
    private $isFullyShipped;

    /**
     * @var string
     */
    private $shippedOrderNumber;


    /**
     *
     */
    public function __construct()
    {
        $this->isFullyShipped = false;
        $this->shippedOrderNumber = '';
    }


    /**
     * @return false
     */
    public function isFullyShipped(): bool
    {
        return $this->isFullyShipped;
    }

    /**
     * @return string
     */
    public function getShippedOrderNumber(): string
    {
        return $this->shippedOrderNumber;
    }

    /**
     * @param OrderEntity $order
     * @param null|TrackingData $tracking
     * @param array $shippingItems
     * @param Context $context
     * @return Shipment
     */
    public function shipOrder(OrderEntity $order, ?TrackingData $tracking, array $shippingItems, Context $context): Shipment
    {
        $this->isFullyShipped = true;
        $this->shippedOrderNumber = $order->getOrderNumber();

        return new Shipment(new MollieApiClient());
    }

    /**
     * @param OrderEntity $order
     * @param string $itemIdentifier
     * @param int $quantity
     * @param null|TrackingData $tracking
     * @param Context $context
     * @return Shipment
     */
    public function shipItem(OrderEntity $order, string $itemIdentifier, int $quantity, ?TrackingData $tracking, Context $context): Shipment
    {
        return new Shipment(new MollieApiClient());
    }

    /**
     * @param OrderEntity $order
     * @param null|TrackingData $tracking
     * @param Context $context
     * @return Shipment
     */
    public function shipOrderRest(OrderEntity $order, ?TrackingData $tracking, Context $context): Shipment
    {
        $this->isFullyShipped = true;
        $this->shippedOrderNumber = $order->getOrderNumber();

        return new Shipment(new MollieApiClient());
    }

    public function getStatus(string $orderId, Context $context): array
    {
        // TODO: Implement getStatus() method.
    }

    public function getTotals(string $orderId, Context $context): array
    {
        // TODO: Implement getTotals() method.
    }
}
