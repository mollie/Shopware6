<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\OrderDeliveryEntity;

use Kiener\MolliePayments\Struct\OrderXEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class OrderDeliveryEntityAttributes extends OrderXEntityAttributes
{
    private OrderDeliveryEntity $deliveryEntity;

    public function __construct(OrderDeliveryEntity $entity)
    {
        parent::__construct($entity);
        $this->deliveryEntity = $entity;
    }
}
