<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Kiener\MolliePayments\Service\MollieApi\Models\MollieShippingItem;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;
use Mollie\Api\Types\OrderLineType;

class Shipment implements ShipmentInterface
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

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @throws ApiException
     * @return ShipmentCollection<\Mollie\Api\Resources\Shipment>
     */
    public function getShipments(string $mollieOrderId, string $salesChannelId): ShipmentCollection
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, ['embed' => 'shipments']);
        return $mollieOrder->shipments();
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param MollieShippingItem[] $items
     * @param null|ShipmentTrackingInfoStruct $tracking
     * @throws \Exception
     * @return MollieShipment
     */
    public function shipOrder(string $mollieOrderId, string $salesChannelId, array $items, ?ShipmentTrackingInfoStruct $tracking): MollieShipment
    {
        try {
            $options = [];

            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $trackingData = $tracking->toArray();

                /** Make sure that tracking data is only set when the code has a value */
                if ($trackingData['code'] !== '') {
                    $options['tracking'] = $trackingData;
                }
            }

            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);

            # if we have no items
            # then simply ship all
            if (empty($items)) {
                return $mollieOrder->shipAll($options);
            }

            # if we have provided items,
            # we need to build the structure first
            foreach ($items as $item) {
                $options['lines'][] = [
                    'id' => $item->getMollieItemId(),
                    'quantity' => $item->getQuantity(),
                ];
            }

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

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param string $mollieOrderLineId
     * @param int $quantity
     * @param null|ShipmentTrackingInfoStruct $tracking
     * @return MollieShipment
     */
    public function shipItem(string $mollieOrderId, string $salesChannelId, string $mollieOrderLineId, int $quantity, ?ShipmentTrackingInfoStruct $tracking): MollieShipment
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

            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $trackingData = $tracking->toArray();

                /** Make sure that tracking data is only set when the code has a value */
                if ($trackingData['code'] !== '') {
                    $options['tracking'] = $trackingData;
                }
            }

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

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return array<mixed>
     */
    public function getStatus(string $mollieOrderId, string $salesChannelId): array
    {
        $lineItems = [];

        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);

        foreach ($mollieOrder->lines() as $mollieOrderLine) {
            /** @var OrderLine $mollieOrderLine */
            if ($mollieOrderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                continue;
            }

            $orderLineItemId = $mollieOrderLine->metadata->orderLineItemId ?? null;
            if (empty($orderLineItemId)) {
                continue;
            }

            $lineItems[$orderLineItemId] = [
                'id' => $orderLineItemId,
                'mollieOrderLineId' => $mollieOrderLine->id,
                'quantity' => $mollieOrderLine->quantity,
                'quantityShippable' => $mollieOrderLine->shippableQuantity,
                'quantityShipped' => $mollieOrderLine->quantityShipped,
            ];
        }

        return $lineItems;
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return array<string, numeric>
     */
    public function getTotals(string $mollieOrderId, string $salesChannelId): array
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);

        $totalAmount = 0.0;
        $totalQuantity = 0;

        foreach ($mollieOrder->lines() as $mollieOrderLine) {
            /** @var OrderLine $mollieOrderLine */
            if ($mollieOrderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                continue;
            }

            /** @phpstan-ignore-next-line */
            if ($mollieOrderLine->amountShipped) {
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
