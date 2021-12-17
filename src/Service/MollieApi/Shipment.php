<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;
use Mollie\Api\Types\OrderLineType;
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

    public function getTotals(string $mollieOrderId, string $salesChannelId): array
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);

        $totalAmount = 0.0;
        $totalQuantity = 0;

        foreach($mollieOrder->lines() as $mollieOrderLine) {
            /** @var OrderLine $mollieOrderLine */
            if($mollieOrderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                continue;
            }

            if($mollieOrderLine->amountShipped)
            {
                $totalAmount += floatval($mollieOrderLine->amountShipped->value);
            }

            $totalQuantity += $mollieOrderLine->quantityShipped;
        }

        return [
            'amount' => $totalAmount,
            'quantity' => $totalQuantity,
        ];
    }
}
