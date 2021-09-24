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

    public function __construct(
        Order $orderApiService
    )
    {
        $this->orderApiService = $orderApiService;
    }

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

    public function shipItem(
        string $mollieOrderId,
        string $salesChannelId,
        string $mollieOrderLineId,
        int    $quantity,
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
            return $mollieOrder->shipAll($options);
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
