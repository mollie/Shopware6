<?php

namespace Kiener\MolliePayments\Service\Transition;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;

interface DeliveryTransitionServiceInterface
{
    public function reOpenDelivery(OrderDeliveryEntity $delivery, Context $context): void;
    public function cancelDelivery(OrderDeliveryEntity $delivery, Context $context): void;
    public function shipDelivery(OrderDeliveryEntity $delivery, Context $context): void;
    public function partialShipDelivery(OrderDeliveryEntity $delivery, Context $context): void;
    public function returnDelivery(OrderDeliveryEntity $delivery, Context $context): void;
    public function partialReturnDelivery(OrderDeliveryEntity $delivery, Context $context): void;
}
