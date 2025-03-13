<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundData;

use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\AbstractItem;
use Mollie\Api\Resources\Refund;

class RefundData
{
    public const ROUNDING_ITEM_LABEL = 'ROUNDING_DIFF';
    /**
     * @var AbstractItem[]
     */
    private $orderItems;

    /**
     * @var array<Refund>
     */
    private $refunds;

    /**
     * @var float
     */
    private $amountVouchers;

    /**
     * @var float
     */
    private $amountPendingRefunds;

    /**
     * @var float
     */
    private $amountCompletedRefunds;

    /**
     * @var float
     */
    private $amountRemaining;

    /**
     * @var float
     */
    private $roundingItemTotal;

    /**
     * @var string
     */
    private $taxStatus;

    /**
     * @param AbstractItem[] $cartItems
     * @param Refund[] $refunds
     */
    public function __construct(array $cartItems, array $refunds, float $amountVouchers, float $amountPendingRefunds, float $amountCompletedRefunds, float $amountRemaining, float $roundingItemTotal, string $taxStatus)
    {
        $this->orderItems = $cartItems;
        $this->refunds = $refunds;
        $this->amountVouchers = $amountVouchers;
        $this->amountPendingRefunds = $amountPendingRefunds;
        $this->amountCompletedRefunds = $amountCompletedRefunds;
        $this->amountRemaining = $amountRemaining;
        $this->roundingItemTotal = $roundingItemTotal;
        $this->taxStatus = $taxStatus;
    }

    /**
     * @return AbstractItem[]
     */
    public function getOrderItems(): array
    {
        return $this->orderItems;
    }

    /**
     * @return Refund[]
     */
    public function getRefunds(): array
    {
        return $this->refunds;
    }

    public function getAmountVouchers(): float
    {
        return $this->amountVouchers;
    }

    public function getAmountPendingRefunds(): float
    {
        return $this->amountPendingRefunds;
    }

    public function getAmountCompletedRefunds(): float
    {
        return $this->amountCompletedRefunds;
    }

    public function getAmountRemaining(): float
    {
        return $this->amountRemaining;
    }

    public function getRoundingItemTotal(): float
    {
        return $this->roundingItemTotal;
    }

    public function getTaxStatus(): string
    {
        return $this->taxStatus;
    }

    /**
     * @return array<mixed>
     */
    public function toArray()
    {
        $hydratedOrderItems = [];

        /** @var AbstractItem $item */
        foreach ($this->orderItems as $item) {
            $hydratedOrderItems[] = $item->toArray();
        }

        /** @var array<mixed> $refundsArray */
        $refundsArray = $this->refunds;
        foreach ($refundsArray as $refundIndex => $refund) {
            if (isset($refund['metadata']) && property_exists($refund['metadata'], 'composition') && is_array($refund['metadata']->composition)) {
                foreach ($refund['metadata']->composition as $compositionIndex => $composition) {
                    if ((bool) $composition['swReference'] === false) {
                        $refundsArray[$refundIndex]['metadata']->composition[$compositionIndex]['label'] = self::ROUNDING_ITEM_LABEL;
                    }
                }
            }
        }

        return [
            'totals' => [
                'remaining' => round($this->amountRemaining, 2),
                'voucherAmount' => round($this->amountVouchers, 2),
                'pendingRefunds' => round($this->amountPendingRefunds, 2),
                'refunded' => round($this->amountCompletedRefunds, 2),
                'roundingDiff' => round($this->roundingItemTotal, 2),
            ],
            'cart' => $hydratedOrderItems,
            'refunds' => $refundsArray,
            'taxStatus' => $this->taxStatus,
        ];
    }
}
