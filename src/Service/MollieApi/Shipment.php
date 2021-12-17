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
        string $salesChannelId
    ): ShipmentCollection
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, ['embed' => 'shipments']);
        return $mollieOrder->shipments();
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return MollieShipment
     */
    public function shipOrder(
        string $mollieOrderId,
        string $salesChannelId
    ): MollieShipment
    {
        try {
            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);
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
     * @return MollieShipment
     */
    public function shipItem(
        string $mollieOrderId,
        string $salesChannelId,
        string $mollieOrderLineId,
        int $quantity
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

            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);
            return $mollieOrder->createShipment($options);
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException(
                $mollieOrderId,
                [
                    'salesChannelId' => $salesChannelId,
                    'options' => $options
                ],
                $e
            );
        }
    }
}
