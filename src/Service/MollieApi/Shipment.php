<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;
use Shopware\Core\Framework\Context;

class Shipment
{
    /**
     * @var Order
     */
    private $orderApiService;

    /**
     * @param Order $orderApiService
     */
    public function __construct(Order $orderApiService)
    {
        $this->orderApiService = $orderApiService;
    }

    public function getShipments(
        string $mollieOrderId,
        string $salesChannelId,
        Context $context
    ): ShipmentCollection
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, $context, ['embed' => 'shipments']);
        return $mollieOrder->shipments();
    }

    /**
     * @param string $mollieOrderId
     * @param string $mollieOrderLineId
     * @param string $salesChannelId
     * @param Context $context
     * @return ShipmentCollection
     */
    public function getShipmentsForLineItem(
        string $mollieOrderId,
        string $mollieOrderLineId,
        string $salesChannelId,
        Context $context
    ): ShipmentCollection
    {
        $shipments = $this->getShipments($mollieOrderId, $salesChannelId, $context);
        $filteredShipments = new ShipmentCollection(0, $shipments->_links);

        /** @var MollieShipment $shipment */
        foreach ($shipments as $shipment) {
            foreach ($shipment->lines() as $line) {
                if ($line->id === $mollieOrderLineId) {
                    $filteredShipments[] = $shipment;
                    $filteredShipments->count += 1;
                    break;
                }
            }
        }

        return $filteredShipments;
    }

    /**
     * @param string $mollieOrderId
     * @param string $mollieShipmentId
     * @param string $salesChannelId
     * @param Context $context
     * @return MollieShipment
     */
    public function getShipment(
        string $mollieOrderId,
        string $mollieShipmentId,
        string $salesChannelId,
        Context $context
    ): MollieShipment
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, $context);
        return $mollieOrder->getShipment($mollieShipmentId);
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param Context $context
     * @return MollieShipment
     */
    public function shipOrder(
        string $mollieOrderId,
        string $salesChannelId,
        Context $context
    ): MollieShipment
    {
        try {
            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, $context);
            return $mollieOrder->shipAll();
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException(
                $mollieOrderId,
                [
                    'salesChannelId' => $salesChannelId
                ],
                $e
            );
        }
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param string $mollieOrderLineId
     * @param int $quantity
     * @param Context $context
     * @return MollieShipment
     */
    public function shipItem(
        string $mollieOrderId,
        string $salesChannelId,
        string $mollieOrderLineId,
        int $quantity,
        Context $context
    ): MollieShipment
    {
        try {
            $options = [
                'lines' => [
                    [
                        'id' => $mollieOrderLineId,
                        'quantity' => $quantity
                    ]
                ]
            ];

            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, $context);
            return $mollieOrder->createShipment($options);
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException(
                $mollieOrderId,
                [
                    'salesChannelId' => $salesChannelId
                ],
                $e
            );
        }
    }
}
