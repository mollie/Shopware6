<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1678106061OrderVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678106061;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `mollie_refund`
ADD COLUMN `order_version_id` BINARY(16) NULL DEFAULT NULL AFTER `order_id`
SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
UPDATE `mollie_refund`
SET `order_version_id` = :orderVersionId
SQL;

        $connection->executeStatement($sql, ['orderVersionId' => Defaults::LIVE_VERSION]);

        $sql = <<<SQL
ALTER TABLE `mollie_refund`
CHANGE `order_version_id` `order_version_id` BINARY(16) NOT NULL,
ADD KEY `fk.mollie_refund.order_id` (`order_id`,`order_version_id`),
ADD CONSTRAINT `fk.mollie_refund.order_id` FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
