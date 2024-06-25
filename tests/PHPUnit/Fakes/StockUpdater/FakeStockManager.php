<?php

namespace MolliePayments\Tests\Fakes\StockUpdater;

use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class FakeStockManager implements StockManagerInterface
{
    /**
     * @var bool
     */
    private $called;

    /**
     * @var string
     */
    private $productID;

    /**
     * @var string
     */
    private $lineItemLabel;

    /**
     * @var string
     */
    private $quantity;

    /**
     * @var string
     */
    private $mollieRefundID;

    /**
     *
     */
    public function __construct()
    {
        $this->called = false;
    }

    /**
     * @return bool
     */
    public function isCalled(): bool
    {
        return $this->called;
    }

    /**
     * @return string
     */
    public function getLineItemLabel(): string
    {
        return $this->lineItemLabel;
    }

    /**
     * @return string
     */
    public function getProductID(): string
    {
        return $this->productID;
    }

    /**
     * @return string
     */
    public function getQuantity(): string
    {
        return $this->quantity;
    }

    /**
     * @return string
     */
    public function getMollieRefundID(): string
    {
        return $this->mollieRefundID;
    }


    /**
     * @param OrderLineItemEntity $lineItem
     * @param int $quantity
     * @param string $mollieRefundID
     * @return void
     */
    public function increaseStock(OrderLineItemEntity $lineItem, int $quantity): void
    {
        $this->called = true;

        $this->lineItemLabel = $lineItem->getLabel();
        $this->productID = $lineItem->getReferencedId();
        $this->quantity = $quantity;
    }
}
