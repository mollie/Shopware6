<?php

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class ProductItem extends AbstractItem
{

    /**
     * @var OrderLineItemEntity
     */
    private $lineItem;

    /**
     * @var float
     */
    private $promotionDiscount;

    /**
     * @var int
     */
    private $promotionAffectedQuantity;

    /**
     * @var int
     */
    private $alreadyRefundedQty;


    /**
     * @param OrderLineItemEntity $lineItem
     * @param array<mixed> $promotionCompositions
     * @param int $alreadyRefundedQuantity
     */
    public function __construct(OrderLineItemEntity $lineItem, array $promotionCompositions, int $alreadyRefundedQuantity)
    {
        $this->lineItem = $lineItem;
        $this->alreadyRefundedQty = $alreadyRefundedQuantity;

        $this->extractPromotionDiscounts($promotionCompositions);
    }

    /**
     * @param array<mixed> $promotionCompositions
     * @return void
     */
    private function extractPromotionDiscounts(array $promotionCompositions)
    {
        $this->promotionDiscount = 0;
        $this->promotionAffectedQuantity = 0;

        foreach ($promotionCompositions as $composition) {
            foreach ($composition as $compItem) {

                # the ID is the reference ID
                # if they match, then our current line item was in that promotion
                if ($compItem['id'] === $this->lineItem->getReferencedId()) {
                    $this->promotionDiscount += round((float)$compItem['discount'], 2);
                    $this->promotionAffectedQuantity += (int)$compItem['quantity'];
                }
            }
        }
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
            false,
            false,
            $this->lineItem->getUnitPrice(),
            $this->lineItem->getQuantity(),
            $this->lineItem->getTotalPrice(),
            $this->promotionDiscount,
            $this->promotionAffectedQuantity,
            $this->alreadyRefundedQty
        );
    }

    /**
     * @return bool
     */
    public function isPromotion(): bool
    {
        if ($this->lineItem->getPayload() === null) {
            return false;
        }

        return isset($this->lineItem->getPayload()['composition']);
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
        if ($this->isPromotion()) {
            $productNumber = (string)$this->lineItem->getReferencedId();
        } else {
            $productNumber = (string)$this->lineItem->getPayload()['productNumber'];
        }

        return $productNumber;
    }

}
