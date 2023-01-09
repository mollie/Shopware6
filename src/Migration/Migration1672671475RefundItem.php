<?php declare(strict_types=1);

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
        $sql = <<<SQL
  CREATE TABLE IF NOT EXISTS `mollie_refund_item` (
                      `id` binary(16) NOT NULL,
                      `line_item_id` binary(16) NULL,
                      `mollie_line_id` VARCHAR(255),
                      `type` VARCHAR(255),
                      `refund_id` VARCHAR(255),
                      `quantity` INT(10),
                      `amount` DOUBLE,
                      `created_at` datetime(3) NOT NULL,
                      `updated_at` datetime(3) DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `fk.order_line_item_id` (`line_item_id`),
                      CONSTRAINT `fk.order_line_item_id` FOREIGN KEY (`line_item_id`) REFERENCES `order_line_item` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
