<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\LoggerService;
use Shopware\Core\Framework\Context;

interface MollieShipmentInterface
{

    /**
     * @param string $orderDeliveryId
     * @param Context $context
     * @return bool
     */
    public function setShipment(string $orderDeliveryId, Context $context): bool;

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrder(string $orderNumber, Context $context): \Mollie\Api\Resources\Shipment;

    /**
     * @param string $orderNumber
     * @param string $itemIdentifier
     * @param int $quantity
     * @param Context $context
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipItem(string $orderNumber, string $itemIdentifier, int $quantity, Context $context): \Mollie\Api\Resources\Shipment;

}
