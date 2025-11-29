<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes\StockUpdater;

use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
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
     * @var int
     */
    private $quantity;

    /**
     * @var string
     */
    private $mollieRefundID;

    public function __construct()
    {
        $this->called = false;
    }

    public function isCalled(): bool
    {
        return $this->called;
    }

    public function getLineItemLabel(): string
    {
        return $this->lineItemLabel;
    }

    public function getProductID(): string
    {
        return $this->productID;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getMollieRefundID(): string
    {
        return $this->mollieRefundID;
    }

    public function increaseStock(OrderLineItemEntity $lineItem, int $quantity): void
    {
        $this->called = true;

        $this->lineItemLabel = $lineItem->getLabel();
        $this->productID = $lineItem->getReferencedId();
        $this->quantity = $quantity;
    }
}
