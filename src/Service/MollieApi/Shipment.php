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

            $mollieOrder = $apiClient->orders->get($mollieOrderId);

            if (!($tracking instanceof ShipmentTrackingInfoStruct)) {
                return $mollieOrder->shipAll();
            }

            $options = [
                'tracking' => $tracking->toArray()
            ];

            return $mollieOrder->shipAll($options);
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
        string                      $mollieOrderId,
        string                      $salesChannelId,
        string                      $mollieOrderLineId,
        ?int                        $quantity = null,
        ?ShipmentTrackingInfoStruct $tracking = null
    ): MollieShipment
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);
            $mollieOrder = $apiClient->orders->get($mollieOrderId);

            $lineItem = ['id' => $mollieOrderLineId];
            if (is_int($quantity)) {
                $lineItem['quantity'] = $quantity;
            }

            $options = [
                'lines' => [
                    $lineItem
                ]
            ];

            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $options['tracking'] = $tracking->toArray();
            }

            return $mollieOrder->shipAll($options);
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
        string                     $mollieOrderId,
        string                     $salesChannelId,
        string                     $mollieShipmentId,
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
