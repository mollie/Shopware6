<?php

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class DeliveryItem extends AbstractItem
{
    /**
     * @var OrderDeliveryEntity
     */
    private $delivery;

    /**
     * @var int
     */
    private $alreadyRefundedQty;


    /**
     * @param OrderDeliveryEntity $delivery
     * @param int $alreadyRefundedQuantity
     */
    public function __construct(OrderDeliveryEntity $delivery, int $alreadyRefundedQuantity)
    {
        $this->delivery = $delivery;
        $this->alreadyRefundedQty = $alreadyRefundedQuantity;
    }


    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $label = ($this->delivery->getShippingMethod() !== null) ? (string)$this->delivery->getShippingMethod()->getName() : 'UNKNOWN NAME';

        return $this->buildArray(
            $this->delivery->getId(),
            $label,
            'SHIPPING',
            false,
            true,
            $this->delivery->getShippingCosts()->getUnitPrice(),
            $this->delivery->getShippingCosts()->getQuantity(),
            $this->delivery->getShippingCosts()->getTotalPrice(),
            0,
            0,
            $this->alreadyRefundedQty
        );
    }
}
