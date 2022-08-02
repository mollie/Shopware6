<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Mollie\Api\Resources\Shipment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface MollieShipmentInterface
{
    /**
     * @param string $orderDeliveryId
     * @param Context $context
     * @return bool
     */
    public function setShipment(string $orderDeliveryId, Context $context): bool;

    /**
     * @param string $orderId
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipOrderByOrderId(string $orderId, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment;

    /**
     * @param string $orderNumber
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipOrderByOrderNumber(string $orderNumber, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment;

    /**
     * @param OrderEntity $order
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipOrder(OrderEntity $order, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment;

    /**
     * @param string $orderId
     * @param string $itemIdentifier
     * @param int $quantity
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipItemByOrderId(string $orderId, string $itemIdentifier, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment;

    /**
     * @param string $orderNumber
     * @param string $itemIdentifier
     * @param int $quantity
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return Shipment
     */
    public function shipItemByOrderNumber(string $orderNumber, string $itemIdentifier, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment;
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
    public function shipItem(OrderEntity $order, string $itemIdentifier, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): Shipment;
}
