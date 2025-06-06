<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1669117848SubscriptionHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1669117848;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS mollie_subscription_history (
                      id binary(16) NOT NULL,
                      subscription_id binary(16) NOT NULL,
                      status_from VARCHAR(255),
                      status_to VARCHAR(255),
                      comment VARCHAR(255),
                      mollie_id VARCHAR(255),
                      created_at datetime(3) NOT NULL,
                      updated_at datetime(3) DEFAULT NULL,
                      PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                '
        );

        $this->createColumn('mollie_subscription', 'mandate_id', 'VARCHAR(100)', $connection);
        $this->createColumn('mollie_subscription', 'status', 'VARCHAR(100)', $connection);

        $this->buildIndex('mollie_subscription_history', 'idx.mollie_subscription_history.id', 'id', $connection);
        $this->buildIndex('mollie_subscription_history', 'idx.mollie_subscription_history.subscription_id', 'subscription_id', $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @throws Exception
     */
    private function createColumn(string $table, string $column, string $type, Connection $connection): void
    {
        $colQuery = $connection->executeQuery('SHOW COLUMNS FROM ' . $table . " LIKE '" . $column . "'")->fetchAssociative();

        if ($colQuery === false) {
            $connection->executeStatement('ALTER TABLE ' . $table . ' ADD ' . $column . ' ' . $type);
        }
    }

    /**
     * @throws Exception
     */
    private function buildIndex(string $table, string $indexName, string $targetField, Connection $connection): void
    {
        $indexExistsCheck = $connection->executeQuery("
            SELECT COUNT(1) indexIsThere 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema=DATABASE() AND table_name='" . $table . "' AND index_name='" . $indexName . "';
        ")->fetchAssociative();

        $isExisting = ((int) $indexExistsCheck['indexIsThere'] === 1);

        if (! $isExisting) {
            $connection->executeStatement('CREATE INDEX `' . $indexName . '` ON ' . $table . ' (' . $targetField . ');');
        }
    }
}
