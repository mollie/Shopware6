<?php

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

abstract class AbstractItem
{
    /**
     * @param string $id
     * @param string $label
     * @param string $referenceNumber
     * @param bool $isPromotion
     * @param bool $isDelivery
     * @param float $unitPrice
     * @param int $quantity
     * @param float $totalPrice
     * @param float $promotionDiscount
     * @param int $promotionAffectedQty
     * @param int $refundedQty
     * @return array<mixed>
     */
    protected function buildArray(string $id, string $label, string $referenceNumber, bool $isPromotion, bool $isDelivery, float $unitPrice, int $quantity, float $totalPrice, float $promotionDiscount, int $promotionAffectedQty, int $refundedQty): array
    {
        return [
            'refunded' => $refundedQty,
            'shopware' => [
                'id' => $id,
                'label' => $label,
                'unitPrice' => round($unitPrice, 2),
                'quantity' => $quantity,
                'totalPrice' => round($totalPrice, 2),
                'discountedPrice' => round($totalPrice - $promotionDiscount, 2),
                'productNumber' => $referenceNumber,
                'promotion' => [
                    'discount' => $promotionDiscount,
                    'quantity' => $promotionAffectedQty,
                ],
                'isPromotion' => $isPromotion,
                'isDelivery' => $isDelivery,
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    abstract public function toArray(): array;
}
