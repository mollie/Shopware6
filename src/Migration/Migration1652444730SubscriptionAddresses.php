<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1652444730SubscriptionAddresses extends MigrationStep
{
    /**
     * @return int
     */
    public function getCreationTimestamp(): int
    {
        return 1652444730;
    }

    /**
     * @param Connection $connection
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Connection $connection): void
    {
        $connection->exec(
            "CREATE TABLE IF NOT EXISTS mollie_subscription_address (
                      `id` binary(16) NOT NULL,
                      `subscription_id` binary(16) NOT NULL,
                      `salutation_id` binary(16) DEFAULT NULL,
                      `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `department` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `country_id` binary(16) NOT NULL,
                      `country_state_id` binary(16) DEFAULT NULL,
                      `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `created_at` datetime(3) NOT NULL,
                      `updated_at` datetime(3) DEFAULT NULL,
                      PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                "
        );


        $this->createColumn('mollie_subscription', 'billing_address_id', 'binary(16)', $connection);
        $this->createColumn('mollie_subscription', 'shipping_address_id', 'binary(16)', $connection);

        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.billing_address_id', 'billing_address_id', $connection);
        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.shipping_address_id', 'shipping_address_id', $connection);

        $this->buildIndex('mollie_subscription_address', 'idx.mollie_subscription_address.id', 'id', $connection);
        $this->buildIndex('mollie_subscription_address', 'idx.mollie_subscription_address.subscription_id', 'subscription_id', $connection);
    }

    /**
     * @param Connection $connection
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $type
     * @param Connection $connection
     * @throws Exception
     * @return void
     */
    private function createColumn(string $table, string $column, string $type, Connection $connection): void
    {
        $colQuery = $connection->executeQuery("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'")->fetch();

        if ($colQuery === false) {
            $connection->exec("ALTER TABLE " . $table . " ADD " . $column . " " . $type);
        }
    }

    /**
     * @param string $table
     * @param string $indexName
     * @param string $targetField
     * @param Connection $connection
     * @throws Exception
     * @return void
     */
    private function buildIndex(string $table, string $indexName, string $targetField, Connection $connection): void
    {
        $indexExistsCheck = $connection->executeQuery("
            SELECT COUNT(1) indexIsThere 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema=DATABASE() AND table_name='" . $table . "' AND index_name='" . $indexName . "';
        ")->fetch();

        $isExisting = ((int)$indexExistsCheck['indexIsThere'] === 1);

        if (!$isExisting) {
            $connection->exec("CREATE INDEX `" . $indexName . "` ON " . $table . " (" . $targetField . ");");
        }
    }
}
