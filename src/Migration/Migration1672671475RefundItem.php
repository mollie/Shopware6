<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1672671475RefundItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1672671475;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
  CREATE TABLE IF NOT EXISTS `mollie_refund_item` (
                      `id` binary(16) NOT NULL,
                      `order_line_item_id` binary(16) NULL,
                      `order_line_item_version_id` binary(16) NULL,
                      `mollie_line_id` VARCHAR(255),
                      `type` VARCHAR(255),
                      `label` VARCHAR(255),
                      `refund_id` binary(16) NOT NULL,
                      `quantity` INT(10),
                      `amount` DOUBLE,
                      `created_at` datetime(3) NOT NULL,
                      `updated_at` datetime(3) DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `fk.order_line_item_id` (`order_line_item_id`,`order_line_item_version_id`),
                      CONSTRAINT `fk.order_line_item_id` FOREIGN KEY (`order_line_item_id`,`order_line_item_version_id`) REFERENCES `order_line_item` (`id`,`version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                      KEY `fk.refund_id` (`refund_id`),
                      CONSTRAINT `fk.refund_id` FOREIGN KEY (`refund_id`) REFERENCES `mollie_refund` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
