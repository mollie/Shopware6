<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1669796049CreateRefund extends MigrationStep
{
    /**
     * @return int
     */

    public function getCreationTimestamp(): int
    {
        return 1669796049;
    }

    /**
     * @param Connection $connection
     * @throws \Doctrine\DBAL\Exception
     * @return void
     */
    public function update(Connection $connection): void
    {
        $connection->exec(
            "
                CREATE TABLE IF NOT EXISTS `mollie_refund` (
                  `id` BINARY(16) NOT NULL,
                  `order_id` BINARY(16) NULL,
                  `mollie_refund_id` VARCHAR(255) NULL,
                  `public_description` LONGTEXT NULL,
                  `internal_description` LONGTEXT NULL,
                  `created_at` DATETIME(3) NOT NULL,
                  `updated_at` DATETIME(3) NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
                "
        );
    }

    /**
     * @param Connection $connection
     * @return void
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
