<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1678106061OrderVersion extends MigrationStep
{
    /**
     * @return int
     */
    public function getCreationTimestamp(): int
    {
        return 1678106061;
    }

    /**
     * @param Connection $connection
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $utils = new MigrationUtils($connection);

        # create new column for the version_id
        $utils->createColumn(
            'mollie_refund',
            'order_version_id',
            'BINARY(16)',
            'NULL',
            'order_id'
        );

        # update existing entries and set the order_version_id to the
        # latest default version ID from the Shopware constant (that's totally fine now)
        $connection->exec("UPDATE `mollie_refund` SET `order_version_id` =X'" . Defaults::LIVE_VERSION . "' WHERE order_version_id IS NULL");

        # change to NOT-NULL now that we have updated everything
        $sql = "ALTER TABLE `mollie_refund` CHANGE `order_version_id` `order_version_id` BINARY(16) NOT NULL";
        $connection->exec($sql);

        # add a key that consists of order_id and version_id
        $utils->addKey(
            'mollie_refund',
            'fk.mollie_refund.order_id',
            ['order_id', 'order_version_id']
        );

        # also add constraint to allow order deletion
        $utils->addConstraint(
            'mollie_refund',
            'fk.mollie_refund.order_id',
            "FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE"
        );
    }

    /**
     * @param Connection $connection
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
