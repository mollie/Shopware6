<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1669796049CreateRefund extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1669796049;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            '
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
                '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
