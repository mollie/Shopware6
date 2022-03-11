<?php

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class PromotionItem extends AbstractItem
{

    /**
     * @var OrderLineItemEntity
     */
    private $lineItem;

    /**
     * @var int
     */
    private $alreadyRefundedQty;


    /**
     * @param OrderLineItemEntity $lineItem
     * @param int $alreadyRefundedQuantity
     */
    public function __construct(OrderLineItemEntity $lineItem, int $alreadyRefundedQuantity)
    {
        $this->lineItem = $lineItem;
        $this->alreadyRefundedQty = $alreadyRefundedQuantity;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->buildArray(
            $this->lineItem->getId(),
            $this->lineItem->getLabel(),
            $this->getProductNumber(),
            true,
            false,
            $this->lineItem->getUnitPrice(),
            $this->lineItem->getQuantity(),
            $this->lineItem->getTotalPrice(),
            0,
            0,
            $this->alreadyRefundedQty
        );
    }

    /**
     * @return string
     */
    public function getProductNumber(): string
    {
        if ($this->lineItem->getPayload() === null) {
            return '';
        }

        # for promotions we use the voucher code as number to display
        # this one is in the reference ID
        return (string)$this->lineItem->getReferencedId();
    }

}
