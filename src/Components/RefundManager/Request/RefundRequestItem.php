<?php

namespace Kiener\MolliePayments\Components\RefundManager\Request;

class RefundRequestItem
{
    /**
     * @var string
     */
    private $lineId;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var int
     */
    private $stockQty;


    /**
     * @param string $lineId
     * @param float $amount
     * @param int $quantity
     * @param int $stockQty
     */
    public function __construct(string $lineId, float $amount, int $quantity, int $stockQty)
    {
        $this->lineId = $lineId;
        $this->amount = $amount;
        $this->quantity = $quantity;
        $this->stockQty = $stockQty;
    }


    /**
     * @return string
     */
    public function getLineId(): string
    {
        return $this->lineId;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return int
     */
    public function getStockIncreaseQty(): int
    {
        return $this->stockQty;
    }
}
