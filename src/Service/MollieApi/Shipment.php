<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
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

            $options = $this->addShippingCosts($mollieOrder, $options);
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
            $options = $this->addShippingCosts($mollieOrder, $options);

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
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param array<mixed> $options
     * @return array<mixed>
     */
    private function addShippingCosts(\Mollie\Api\Resources\Order $mollieOrder, array $options): array
    {
        $shippingOptions = [];

        $mollieLines = $mollieOrder->lines();

        $shippableLines = [];

        /**
         * @var OrderLine $line
         */
        foreach ($mollieLines as $line) {
            if ($line->type === OrderLineType::TYPE_SHIPPING_FEE) {
                $shippingOptions[] = [
                    'id' => $line->id,
                    'quantity' => $line->quantity,
                ];
                continue;
            }
            if ($line->shippableQuantity > 0) {
                $shippableLines[$line->id] = $line;
            }
        }


        foreach ($options['lines'] as $line) {
            $shippableLine = $shippableLines[$line['id']]??null;
            if ($shippableLine === null) {
                continue;
            }
            $shippableQuantity = $shippableLine->shippableQuantity - $line['quantity'];
            if ($shippableQuantity === 0) {
                unset($shippableLines[$line['id']]);
            }
        }
        if (count($shippableLines) === 0) {
            $options['lines'] = array_merge($options['lines'], $shippingOptions);
        }


        return $options;
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
        try {
            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);
        } catch (CouldNotFetchMollieOrderException $e) {
            return [
                'amount' => 0.0,
                'quantity' => 0,
                'shippable' => 0
            ];
        }

        $totalAmount = 0.0;
        $totalQuantity = 0;
        $shippableQuantity = 0;
        foreach ($mollieOrder->lines() as $mollieOrderLine) {
            /** @var OrderLine $mollieOrderLine */
            if ($mollieOrderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                continue;
            }

            /** @phpstan-ignore-next-line */
            if ($mollieOrderLine->amountShipped) {
                $totalAmount += floatval($mollieOrderLine->amountShipped->value);
            }
            $shippableQuantity += $mollieOrderLine->shippableQuantity;
            $totalQuantity += $mollieOrderLine->quantityShipped;
        }

        return [
            'amount' => $totalAmount,
            'quantity' => $totalQuantity,
            'shippableQuantity' => $shippableQuantity
        ];
    }
}
