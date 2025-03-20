<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;

class PromotionItem extends AbstractItem
{
    /**
     * @var OrderLineItemEntity
     */
    private $orderLineItem;

    /**
     * @var OrderDeliveryEntity
     */
    private $orderDeliveryItem;

    /**
     * @var int
     */
    private $alreadyRefundedQty;

    /**
     * @param OrderDeliveryEntity|OrderLineItemEntity $lineItem
     */
    private function __construct($lineItem, int $alreadyRefundedQuantity, float $taxTotal, float $taxPerItem, float $taxDiff)
    {
        if ($lineItem instanceof OrderDeliveryEntity) {
            $this->orderDeliveryItem = $lineItem;
        }

        if ($lineItem instanceof OrderLineItemEntity) {
            $this->orderLineItem = $lineItem;
        }

        $this->alreadyRefundedQty = $alreadyRefundedQuantity;

        parent::__construct($taxTotal, $taxPerItem, $taxDiff);
    }

    /**
     * @return PromotionItem
     */
    public static function fromOrderLineItem(OrderLineItemEntity $lineItem, int $alreadyRefundedQuantity, float $taxTotal, float $taxPerItem, float $taxDiff)
    {
        return new PromotionItem($lineItem, $alreadyRefundedQuantity, $taxTotal, $taxPerItem, $taxDiff);
    }

    /**
     * @return PromotionItem
     */
    public static function fromOrderDeliveryItem(OrderDeliveryEntity $lineItem, int $alreadyRefundedQuantity, float $taxTotal, float $taxPerItem, float $taxDiff)
    {
        return new PromotionItem($lineItem, $alreadyRefundedQuantity, $taxTotal, $taxPerItem, $taxDiff);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        if ($this->orderLineItem !== null) {
            return $this->buildArray(
                $this->orderLineItem->getId(),
                $this->orderLineItem->getLabel(),
                $this->getProductNumber(),
                true,
                false,
                $this->orderLineItem->getUnitPrice(),
                $this->orderLineItem->getQuantity(),
                $this->orderLineItem->getTotalPrice(),
                0,
                0,
                0,
                $this->alreadyRefundedQty
            );
        }
        $label = '';

        $method = $this->orderDeliveryItem->getShippingMethod();
        if ($method instanceof ShippingMethodEntity) {
            $label = (string) $method->getName();
        }

        if (empty($label)) {
            $label = 'SHIPPING';
        }

        return $this->buildArray(
            $this->orderDeliveryItem->getId(),
            $label,
            'SHIPPING',
            true,
            false,
            $this->orderDeliveryItem->getShippingCosts()->getTotalPrice(),
            $this->orderDeliveryItem->getShippingCosts()->getQuantity(),
            $this->orderDeliveryItem->getShippingCosts()->getTotalPrice(),
            0,
            0,
            0,
            $this->alreadyRefundedQty
        );
    }

    public function getProductNumber(): string
    {
        // delivery items have no product number
        if ($this->orderDeliveryItem !== null) {
            return '';
        }

        if ($this->orderLineItem->getPayload() === null) {
            return '';
        }

        // for promotions we use the voucher code as number to display
        // this one is in the reference ID
        return (string) $this->orderLineItem->getReferencedId();
    }
}
