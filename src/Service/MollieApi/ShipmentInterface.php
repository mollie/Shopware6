<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Service\MollieApi\Models\MollieShippingItem;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;

interface ShipmentInterface
{

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return array<mixed>
     */
    public function getTotals(string $mollieOrderId, string $salesChannelId): array;

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return array<mixed>
     */
    public function getStatus(string $mollieOrderId, string $salesChannelId): array;

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return ShipmentCollection
     */
    public function getShipments(string $mollieOrderId, string $salesChannelId): ShipmentCollection;

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param MollieShippingItem[] $items
     * @param null|ShipmentTrackingInfoStruct $tracking
     * @return MollieShipment
     */
    public function shipOrder(string $mollieOrderId, string $salesChannelId, array $items, ?ShipmentTrackingInfoStruct $tracking): MollieShipment;

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param string $mollieOrderLineId
     * @param int $quantity
     * @param null|ShipmentTrackingInfoStruct $tracking
     * @return MollieShipment
     */
    public function shipItem(string $mollieOrderId, string $salesChannelId, string $mollieOrderLineId, int $quantity, ?ShipmentTrackingInfoStruct $tracking): MollieShipment;
}
