<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Facade\MollieShipmentInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Shipment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class FakeMollieShipment implements MollieShipmentInterface
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
     * @param string $orderDeliveryId
     * @param Context $context
     * @return bool
     */
    public function setShipment(string $orderDeliveryId, Context $context): bool
    {
        return false;
    }

    /**
     * @param string $orderId
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipOrderByOrderId(string $orderId, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment
    {
        $this->isFullyShipped = true;
        return new Shipment(new MollieApiClient());
    }

    /**
     * @param string $orderNumber
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipOrderByOrderNumber(string $orderNumber, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment
    {
        $this->isFullyShipped = true;
        $this->shippedOrderNumber = $orderNumber;
        return new Shipment(new MollieApiClient());
    }

    /**
     * @param OrderEntity $order
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipOrder(OrderEntity $order, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment
    {
        $this->isFullyShipped = true;
        $this->shippedOrderNumber = $order->getOrderNumber();

        return new Shipment(new MollieApiClient());
    }

    public function shipItemByOrderId(string $orderId, string $itemIdentifier, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment
    {
        return new Shipment(new MollieApiClient());
    }

    public function shipItemByOrderNumber(string $orderNumber, string $itemIdentifier, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment
    {
        return new Shipment(new MollieApiClient());
    }


    /**
     * @param OrderEntity $order
     * @param string $itemIdentifier
     * @param int $quantity
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipItem(OrderEntity $order, string $itemIdentifier, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment
    {
        return new Shipment(new MollieApiClient());
    }

}
