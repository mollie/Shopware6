<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ShipmentManager;

use Kiener\MolliePayments\Components\ShipmentManager\Models\ShipmentLineItem;
use Kiener\MolliePayments\Components\ShipmentManager\Models\TrackingData;
use Mollie\Api\Resources\Shipment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface ShipmentManagerInterface
{
    /**
     * @return array<mixed>
     */
    public function getStatus(string $orderId, Context $context): array;

    /**
     * @return array<string, numeric>
     */
    public function getTotals(string $orderId, Context $context): array;

    /**
     * @param ShipmentLineItem[] $shippingItems
     */
    public function shipOrder(OrderEntity $order, ?TrackingData $tracking, array $shippingItems, Context $context): Shipment;

    public function shipOrderRest(OrderEntity $order, ?TrackingData $tracking, Context $context): Shipment;

    public function shipItem(OrderEntity $order, string $itemIdentifier, int $quantity, ?TrackingData $tracking, Context $context): Shipment;
}
