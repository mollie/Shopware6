<?php

namespace Kiener\MolliePayments\Service\Stock;


use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;


interface StockUpdaterInterface
{

    /**
     * @param string $productID
     * @param int $quantity
     * @return void
     */
    public function increaseStock(string $productID, int $quantity): void;

}
