<?php

namespace Kiener\MolliePayments\Service\Refund\Item;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class MollieRefundItem
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
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var int
     */
    private $resetStock;

    /**
     * @var ?OrderLineItemEntity
     */
    private $orderItem;


    /**
     * @param string $shopwareLineID
     * @param string $mollieLineID
     * @param int $quantity
     * @param float $amount
     * @param int $resetStock
     * @param ?OrderLineItemEntity $orderItem
     */
    public function __construct(string $shopwareLineID, string $mollieLineID, int $quantity, float $amount, int $resetStock, ?OrderLineItemEntity $orderItem)
    {
        $this->shopwareLineID = $shopwareLineID;
        $this->mollieLineID = $mollieLineID;
        $this->quantity = $quantity;
        $this->amount = $amount;
        $this->resetStock = $resetStock;
        $this->orderItem = $orderItem;
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
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return int
     */
    public function getResetStock(): int
    {
        return $this->resetStock;
    }

    /**
     * @return string
     */
    public function getMollieLineID(): string
    {
        return $this->mollieLineID;
    }

    /**
     * @return OrderLineItemEntity
     */
    public function getOrderItem(): ?OrderLineItemEntity
    {
        return $this->orderItem;
    }

    /**
     * @return string
     */
    public function getProductID(): string
    {
        if ($this->orderItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return '';
        }

        return (string)$this->orderItem->getReferencedId();
    }

}
