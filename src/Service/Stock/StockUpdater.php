<?php

namespace Kiener\MolliePayments\Service\Stock;


use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;


class StockUpdater implements StockUpdaterInterface
{

    /**
     * @var Connection
     */
    private $connection;


    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $productID
     * @param int $quantity
     * @return void
     */
    public function increaseStock(string $productID, int $quantity): void
    {
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'UPDATE product SET available_stock = available_stock + :refundQuantity, sales = sales - :refundQuantity, updated_at = :now WHERE id = :id'
            )
        );

        $update->execute([
            'id' => Uuid::fromHexToBytes($productID),
            'refundQuantity' => $quantity,
            'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

}
