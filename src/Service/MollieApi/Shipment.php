<?php

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeShippedException;
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
        string $mollieOrderId,
        string $salesChannelId
    ): MollieShipment
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            $mollieOrder = $apiClient->orders->get($mollieOrderId);

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

    public function shipArticle(
        string $mollieOrderId,
        string $salesChannelId,
        string $mollieOrderLineId,
        ?int   $quantity = null
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
