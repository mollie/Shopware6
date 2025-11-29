<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

use Kiener\MolliePayments\Service\MollieApi\ShipmentInterface;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Shipment;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;

class FakeShipment implements ShipmentInterface
{
    /**
     * @var string
     */
    private $shippedMollieOrderId;

    /**
     * @var bool
     */
    private $shipOrderCalled;

    /**
     * @var bool
     */
    private $shipItemCalled;

    /**
     * @var array<mixed>
     */
    private $shippedItems;

    /**
     * @var ?ShipmentTrackingInfoStruct
     */
    private $shippedTracking;

    /**
     * @var int
     */
    private $shippedItemQty;

    public function getShippedMollieOrderId(): string
    {
        return $this->shippedMollieOrderId;
    }

    /**
     * @return mixed[]
     */
    public function getShippedItems(): array
    {
        return $this->shippedItems;
    }

    public function isShipItemCalled(): bool
    {
        return $this->shipItemCalled;
    }

    public function isShipOrderCalled(): bool
    {
        return $this->shipOrderCalled;
    }

    public function getShippedTracking(): ?ShipmentTrackingInfoStruct
    {
        return $this->shippedTracking;
    }

    public function getShippedItemQty(): int
    {
        return $this->shippedItemQty;
    }

    /**
     * @return array|mixed[]
     */
    public function getTotals(string $mollieOrderId, string $salesChannelId): array
    {
        // TODO: Implement getTotals() method.
    }

    /**
     * @return array|mixed[]
     */
    public function getStatus(string $mollieOrderId, string $salesChannelId): array
    {
        // TODO: Implement getStatus() method.
    }

    public function getShipments(string $mollieOrderId, string $salesChannelId): ShipmentCollection
    {
        // TODO: Implement getShipments() method.
    }

    public function shipOrder(string $mollieOrderId, string $salesChannelId, array $items, ?ShipmentTrackingInfoStruct $tracking = null): MollieShipment
    {
        $this->shipOrderCalled = true;
        $this->shippedMollieOrderId = $mollieOrderId;
        $this->shippedItems = $items;
        $this->shippedTracking = $tracking;

        return new Shipment(new MollieApiClient());
    }

    public function shipItem(string $mollieOrderId, string $salesChannelId, string $mollieOrderLineId, int $quantity, ?ShipmentTrackingInfoStruct $tracking = null): MollieShipment
    {
        $this->shipItemCalled = true;
        $this->shippedMollieOrderId = $mollieOrderId;
        $this->shippedItems = [$mollieOrderLineId];
        $this->shippedTracking = $tracking;
        $this->shippedItemQty = $quantity;

        return new Shipment(new MollieApiClient());
    }
}
