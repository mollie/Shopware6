<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

abstract class AbstractItem
{
    /**
     * @var float
     */
    private $taxTotal;

    /**
     * @var float
     */
    private $taxPerItem;

    /**
     * @var float
     */
    private $taxDiff;

    public function __construct(float $taxTotal, float $taxPerItem, float $taxDiff)
    {
        $this->taxTotal = $taxTotal;
        $this->taxPerItem = $taxPerItem;
        $this->taxDiff = $taxDiff;
    }

    /**
     * @return array<mixed>
     */
    abstract public function toArray(): array;

    /**
     * @return array<mixed>
     */
    protected function buildArray(string $id, string $label, string $referenceNumber, bool $isPromotion, bool $isDelivery, float $unitPrice, int $quantity, float $totalPrice, float $promotionDiscount, int $promotionAffectedQty, float $promotionTaxValue, int $refundedQty): array
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
                    'taxValue' => $promotionTaxValue,
                ],
                'isPromotion' => $isPromotion,
                'isDelivery' => $isDelivery,
                'tax' => [
                    'totalItemTax' => round($this->taxTotal, 2),
                    'perItemTax' => round($this->taxPerItem, 2),
                    'totalToPerItemRoundingDiff' => round($this->taxDiff, 2),
                ],
            ],
        ];
    }
}
