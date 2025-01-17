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
     * @var float
     */
    private $promotionTaxValue;

    /**
     * @var int
     */
    private $alreadyRefundedQty;


    /**
     * @param OrderLineItemEntity $lineItem
     * @param array<mixed> $promotionCompositions
     * @param int $alreadyRefundedQuantity
     * @param float $taxTotal
     * @param float $taxPerItem
     * @param float $taxDiff
     */
    public function __construct(OrderLineItemEntity $lineItem, array $promotionCompositions, int $alreadyRefundedQuantity, float $taxTotal, float $taxPerItem, float $taxDiff)
    {
        $this->lineItem = $lineItem;
        $this->alreadyRefundedQty = $alreadyRefundedQuantity;

        $this->extractPromotionDiscounts($promotionCompositions);

        parent::__construct($taxTotal, $taxPerItem, $taxDiff);
    }

    /**
     * @param array<mixed> $promotionCompositions
     * @return void
     */
    private function extractPromotionDiscounts(array $promotionCompositions)
    {
        $this->promotionDiscount = 0;
        $this->promotionAffectedQuantity = 0;
        $this->promotionTaxValue = 0;

        foreach ($promotionCompositions as $composition) {
            foreach ($composition as $compItem) {
                # the ID is the reference ID
                # if they match, then our current line item was in that promotion
                if ($compItem['id'] === $this->lineItem->getReferencedId()) {
                    $this->promotionDiscount += round((float)$compItem['discount'], 2);
                    $this->promotionAffectedQuantity += (int)$compItem['quantity'];
                    $this->promotionTaxValue += round((float)$compItem['taxValue'], 2);
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
            $this->promotionTaxValue,
            $this->alreadyRefundedQty
        );
    }


    /**
     * @return string
     */
    private function getProductNumber(): string
    {
        if ($this->lineItem->getPayload() === null) {
            return '';
        }

        if (!isset($this->lineItem->getPayload()['productNumber'])) {
            return '';
        }

        return (string)$this->lineItem->getPayload()['productNumber'];
    }

    public function getAlreadyRefundedQty(): int
    {
        return $this->alreadyRefundedQty;
    }

    public function getId(): string
    {
        return $this->lineItem->getId();
    }
}
