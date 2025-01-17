<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ShipmentManager;

use Kiener\MolliePayments\Components\ShipmentManager\Models\ShipmentLineItem;
use Kiener\MolliePayments\Components\ShipmentManager\Models\TrackingData;
use Mollie\Api\Resources\Shipment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface ShipmentManagerInterface
{

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<mixed>
     */
    public function getStatus(string $orderId, Context $context): array;

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<string, numeric>
     */
    public function getTotals(string $orderId, Context $context): array;

    /**
     * @param OrderEntity $order
     * @param null|TrackingData $tracking
     * @param ShipmentLineItem[] $shippingItems
     * @param Context $context
     * @return Shipment
     */
    public function shipOrder(OrderEntity $order, ?TrackingData $tracking, array $shippingItems, Context $context): Shipment;

    /**
     * @param OrderEntity $order
     * @param null|TrackingData $tracking
     * @param Context $context
     * @return Shipment
     */
    public function shipOrderRest(OrderEntity $order, ?TrackingData $tracking, Context $context): Shipment;

    /**
     * @param OrderEntity $order
     * @param string $itemIdentifier
     * @param int $quantity
     * @param null|TrackingData $tracking
     * @param Context $context
     * @return Shipment
     */
    public function shipItem(OrderEntity $order, string $itemIdentifier, int $quantity, ?TrackingData $tracking, Context $context): Shipment;
}
