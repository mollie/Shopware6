<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1711618833SubscriptionCurrency extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1711618833;
    }

    public function update(Connection $connection): void
    {
        // add new columns
        $sql = "ALTER TABLE `mollie_subscription` 
                ADD COLUMN `currency_id` BINARY(16) NULL AFTER `currency`,
                ADD COLUMN `total_rounding` JSON NULL,
                ADD COLUMN `item_rounding` JSON NULL
                ";
        $connection->executeStatement($sql);

        //add foreign key
        $sql = "ALTER TABLE `mollie_subscription` ADD CONSTRAINT `fk.mollie_subscription.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE SET NULL ON UPDATE CASCADE";
        $connection->executeStatement($sql);

        //load used currencies
        $sql = "SELECT DISTINCT `currency` FROM `mollie_subscription`";
        $statement = $connection->executeQuery($sql);
        $currencies = array_keys($statement->fetchAllAssociativeIndexed());

        //get the data for each currency
        $sql = "SELECT HEX(`id`) as `id`,`iso_code`,`item_rounding`,`total_rounding` FROM `currency` WHERE `iso_code` IN(:currencies)";
        $statement = $connection->executeQuery($sql, [
            'currencies' => $currencies
        ], [
            'currencies' => Connection::PARAM_STR_ARRAY
        ]);


        //update currency information
        $sql = "UPDATE `mollie_subscription` SET `currency_id` = :currencyId, `item_rounding` = :itemRounding,`total_rounding` =:totalRounding  WHERE `currency` = :currencyIso";

        $updateStatement = $connection->prepare($sql);

        while ($row = $statement->fetchAssociative()) {
            $updateStatement->executeStatement([
                'currencyId' => Uuid::fromHexToBytes($row['id']),
                'itemRounding' => $row['item_rounding'],
                'totalRounding' => $row['total_rounding'],
                'currencyIso' => $row['iso_code']
            ]);
        }

        //delete unsused column
        $sql = "ALTER TABLE  `mollie_subscription` DROP COLUMN `currency`";
        $connection->executeStatement($sql);
    }

    /**
     * @param Connection $connection
     * @return void
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
