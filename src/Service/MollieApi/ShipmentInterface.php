<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Service\MollieApi\Models\MollieShippingItem;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;

interface ShipmentInterface
{
    /**
     * @return array<mixed>
     */
    public function getTotals(string $mollieOrderId, string $salesChannelId): array;

    /**
     * @return array<mixed>
     */
    public function getStatus(string $mollieOrderId, string $salesChannelId): array;

    public function getShipments(string $mollieOrderId, string $salesChannelId): ShipmentCollection;

    /**
     * @param MollieShippingItem[] $items
     */
    public function shipOrder(string $mollieOrderId, string $salesChannelId, array $items, ?ShipmentTrackingInfoStruct $tracking): MollieShipment;

    public function shipItem(string $mollieOrderId, string $salesChannelId, string $mollieOrderLineId, int $quantity, ?ShipmentTrackingInfoStruct $tracking): MollieShipment;
}
