<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Facade\MollieShipmentInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Shipment;
use Shopware\Core\Framework\Context;

class FakeMollieShipment implements MollieShipmentInterface
{

    /**
     * @var false
     */
    private $isFullyShipped;

    /**
     * @var string
     */
    private $shippedOrderNumber;


    /**
     *
     */
    public function __construct()
    {
        $this->isFullyShipped = false;
        $this->shippedOrderNumber = '';
    }


    /**
     * @return false
     */
    public function isFullyShipped(): bool
    {
        return $this->isFullyShipped;
    }

    /**
     * @return string
     */
    public function getShippedOrderNumber(): string
    {
        return $this->shippedOrderNumber;
    }


    /**
     * @param string $orderDeliveryId
     * @param Context $context
     * @return bool
     */
    public function setShipment(string $orderDeliveryId, Context $context): bool
    {
        return false;
    }

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return Shipment
     */
    public function shipOrder(string $orderNumber, Context $context): \Mollie\Api\Resources\Shipment
    {
        $this->isFullyShipped = true;
        $this->shippedOrderNumber = $orderNumber;

        return new \Mollie\Api\Resources\Shipment(new MollieApiClient());
    }

    /**
     * @param string $orderNumber
     * @param string $itemIdentifier
     * @param int $quantity
     * @param Context $context
     * @return Shipment
     */
    public function shipItem(string $orderNumber, string $itemIdentifier, int $quantity, Context $context): \Mollie\Api\Resources\Shipment
    {
        return new \Mollie\Api\Resources\Shipment(new MollieApiClient());
    }

}