<?php

namespace Kiener\MolliePayments\Service\Refund\Item;

class RefundItem
{
    /**
     * @var string
     */
    private $shopwareLineID;

    /**
     * @var string
     */
    private $mollieLineID;

    /**
     * @var string
     */
    private $shopwareReference;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $amount;


    /**
     * @param string $shopwareLineID
     * @param string $mollieLineID
     * @param string $shopwareReference
     * @param int $quantity
     * @param float $amount
     */
    public function __construct(string $shopwareLineID, string $mollieLineID, string $shopwareReference, int $quantity, float $amount)
    {
        $this->shopwareLineID = $shopwareLineID;
        $this->mollieLineID = $mollieLineID;
        $this->shopwareReference = $shopwareReference;
        $this->quantity = $quantity;
        $this->amount = round($amount, 2);
    }


    /**
     * @return string
     */
    public function getShopwareLineID(): string
    {
        return $this->shopwareLineID;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return string
     */
    public function getShopwareReference(): string
    {
        return $this->shopwareReference;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getMollieLineID(): string
    {
        return $this->mollieLineID;
    }
}
