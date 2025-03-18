<?php
declare(strict_types=1);

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

    public function __construct(string $lineId, float $amount, int $quantity, int $stockQty)
    {
        $this->lineId = $lineId;
        $this->amount = $amount;
        $this->quantity = $quantity;
        $this->stockQty = $stockQty;
    }

    public function getLineId(): string
    {
        return $this->lineId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStockIncreaseQty(): int
    {
        return $this->stockQty;
    }
}
