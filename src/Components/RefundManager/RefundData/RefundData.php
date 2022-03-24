<?php

namespace Kiener\MolliePayments\Components\RefundManager\RefundData;


use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\AbstractItem;
use Mollie\Api\Resources\Refund;

class RefundData
{

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
     * @param AbstractItem[] $cartItems
     * @param Refund[] $refunds
     * @param float $amountVouchers
     * @param float $amountPendingRefunds
     * @param float $amountCompletedRefunds
     * @param float $amountRemaining
     */
    public function __construct(array $cartItems, array $refunds, float $amountVouchers, float $amountPendingRefunds, float $amountCompletedRefunds, float $amountRemaining)
    {
        $this->orderItems = $cartItems;
        $this->refunds = $refunds;
        $this->amountVouchers = $amountVouchers;
        $this->amountPendingRefunds = $amountPendingRefunds;
        $this->amountCompletedRefunds = $amountCompletedRefunds;
        $this->amountRemaining = $amountRemaining;
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

    /**
     * @return float
     */
    public function getAmountVouchers(): float
    {
        return $this->amountVouchers;
    }

    /**
     * @return float
     */
    public function getAmountPendingRefunds(): float
    {
        return $this->amountPendingRefunds;
    }

    /**
     * @return float
     */
    public function getAmountCompletedRefunds(): float
    {
        return $this->amountCompletedRefunds;
    }

    /**
     * @return float
     */
    public function getAmountRemaining(): float
    {
        return $this->amountRemaining;
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

        return [
            'totals' => [
                'remaining' => round($this->amountRemaining, 2),
                'voucherAmount' => round($this->amountVouchers, 2),
                'pendingRefunds' => round($this->amountPendingRefunds, 2),
                'refunded' => round($this->amountCompletedRefunds, 2),
            ],
            'cart' => $hydratedOrderItems,
            'refunds' => $this->refunds,
        ];
    }

}
