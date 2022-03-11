<?php

namespace MolliePayments\Tests\Fakes\StockUpdater;

use Kiener\MolliePayments\Service\Stock\StockUpdaterInterface;

class FakeStockUpdater implements StockUpdaterInterface
{
    /**
     * @var string
     */
    private $productID;

    /**
     * @var string
     */
    private $quantity;


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
     * @param string $productID
     * @param int $quantity
     * @return void
     */
    public function increaseStock(string $productID, int $quantity): void
    {
        $this->productID = $productID;
        $this->quantity = $quantity;
    }

}
