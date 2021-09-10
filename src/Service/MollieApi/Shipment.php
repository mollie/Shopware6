<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Kiener\MolliePayments\Exception\MollieShipmentTrackingInfoCouldNotBeUpdatedException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Shipment as MollieShipment;

class Shipment
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    public function __construct(
        MollieApiFactory $clientFactory
    )
    {
        $this->clientFactory = $clientFactory;
    }

    public function shipOrder(
        string                      $mollieOrderId,
        string                      $salesChannelId,
        ?ShipmentTrackingInfoStruct $tracking = null
    ): MollieShipment
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            $shipmentOptions = [];
            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $shipmentOptions['tracking'] = $tracking->toArray();
            }

            $mollieOrder = $apiClient->orders->get($mollieOrderId);
            return $mollieOrder->shipAll($shipmentOptions);
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException(
                $mollieOrderId,
                [
                    'salesChannelId' => $salesChannelId,
                    'tracking' => ($tracking instanceof ShipmentTrackingInfoStruct) ? $tracking->toArray() : null
                ],
                $e
            );
        }
    }

    public function shipArticle(
        string $mollieOrderId,
        string $salesChannelId,
        string $mollieOrderLineId,
        ?int $quantity = null,
        ?ShipmentTrackingInfoStruct $tracking = null
    ): MollieShipment
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            $shipmentLine = ['id' => $mollieOrderLineId];
            if(is_int($quantity)) {
                $shipmentLine['quantity'] = $quantity;
            }

            $shipmentOptions = [];
            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $shipmentOptions['tracking'] = $tracking->toArray();
            }

            $shipmentOptions['lines'] = [$shipmentLine];

            $mollieOrder = $apiClient->orders->get($mollieOrderId);
            return $mollieOrder->shipAll($shipmentOptions);
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException(
                $mollieOrderId,
                [
                    'salesChannelId' => $salesChannelId,
                    'tracking' => ($tracking instanceof ShipmentTrackingInfoStruct) ? $tracking->toArray() : null
                ],
                $e
            );
        }
    }

    public function updateTracking(
        string $mollieOrderId,
        string $salesChannelId,
        string $mollieShipmentId,
        ShipmentTrackingInfoStruct $tracking
    ): MollieShipment
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            $mollieOrder = $apiClient->orders->get($mollieOrderId);

            $shipment = $mollieOrder->getShipment($mollieShipmentId);
            $shipment->tracking = $tracking->toArray();

            return $shipment->update();
        } catch (ApiException $e) {
            throw new MollieShipmentTrackingInfoCouldNotBeUpdatedException(
                $mollieOrderId,
                $mollieShipmentId,
                [
                    'salesChannelId' => $salesChannelId,
                    'tracking' => $tracking->toArray(),
                ],
                $e
            );
        }
    }
}
