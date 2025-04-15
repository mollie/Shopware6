<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Event\OrderLinesUpdatedEvent;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
use Kiener\MolliePayments\Service\MollieApi\Models\MollieShippingItem;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Shipment as MollieShipment;
use Mollie\Api\Resources\ShipmentCollection;
use Mollie\Api\Types\OrderLineType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Shipment implements ShipmentInterface
{
    /**
     * @var Order
     */
    private $orderApiService;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    public function __construct(Order $orderApiService, EventDispatcherInterface $eventDispatcher,LoggerInterface $logger)
    {
        $this->orderApiService = $orderApiService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * @throws ApiException
     *
     * @return ShipmentCollection<MollieShipment>
     */
    public function getShipments(string $mollieOrderId, string $salesChannelId): ShipmentCollection
    {
        $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId, ['embed' => 'shipments']);

        return $mollieOrder->shipments();
    }

    /**
     * @param MollieShippingItem[] $items
     *
     * @throws \Exception
     */
    public function shipOrder(string $mollieOrderId, string $salesChannelId, array $items, ?ShipmentTrackingInfoStruct $tracking): MollieShipment
    {
        try {
            $options = [];

            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $trackingData = $tracking->toArray();

                /* Make sure that tracking data is only set when the code has a value */
                if ($trackingData['code'] !== '') {
                    $options['tracking'] = $trackingData;
                }
            }

            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);

            // if we have no items
            // then simply ship all
            if (empty($items)) {
                $this->logger->debug('ship all items',[
                    'options' => $options,
                    'mollieOrderId' => $mollieOrderId,
                ]);
                return $mollieOrder->shipAll($options);
            }

            // if we have provided items,
            // we need to build the structure first
            foreach ($items as $item) {
                $options['lines'][] = [
                    'id' => $item->getMollieItemId(),
                    'quantity' => $item->getQuantity(),
                ];
            }

            $this->logger->debug('ship lines items',[
                'options' => $options,
                'mollieOrderId' => $mollieOrderId,
            ]);
            $shipment = $mollieOrder->createShipment($options);

            $this->eventDispatcher->dispatch(new OrderLinesUpdatedEvent($mollieOrder));

            return $shipment;
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException($mollieOrderId, ['salesChannelId' => $salesChannelId], $e);
        }
    }

    public function shipItem(string $mollieOrderId, string $salesChannelId, string $mollieOrderLineId, int $quantity, ?ShipmentTrackingInfoStruct $tracking): MollieShipment
    {
        try {
            $options = [
                'lines' => [
                    [
                        'id' => $mollieOrderLineId,
                        'quantity' => $quantity,
                    ],
                ],
            ];

            if ($tracking instanceof ShipmentTrackingInfoStruct) {
                $trackingData = $tracking->toArray();

                /* Make sure that tracking data is only set when the code has a value */
                if ($trackingData['code'] !== '') {
                    $options['tracking'] = $trackingData;
                }
            }

            $mollieOrder = $this->orderApiService->getMollieOrder($mollieOrderId, $salesChannelId);

            $shipment = $mollieOrder->createShipment($options);
            $this->eventDispatcher->dispatch(new OrderLinesUpdatedEvent($mollieOrder));

            return $shipment;
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeShippedException($mollieOrderId, ['salesChannelId' => $salesChannelId, 'options' => $options], $e);
        }
    }

    /**
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
                'shippable' => 0,
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

            /* @phpstan-ignore-next-line */
            if ($mollieOrderLine->amountShipped) {
                $totalAmount += floatval($mollieOrderLine->amountShipped->value);
            }
            $shippableQuantity += $mollieOrderLine->shippableQuantity;
            $totalQuantity += $mollieOrderLine->quantityShipped;
        }

        return [
            'amount' => $totalAmount,
            'quantity' => $totalQuantity,
            'shippableQuantity' => $shippableQuantity,
        ];
    }
}
