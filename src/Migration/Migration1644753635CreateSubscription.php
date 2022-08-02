<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1644753635CreateSubscription extends MigrationStep
{
    /**
     * @return int
     */
    public function getCreationTimestamp(): int
    {
        return 1644753635;
    }

    /**
     * @param Connection $connection
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $connection->exec(
            "CREATE TABLE IF NOT EXISTS mollie_subscription (
                    id BINARY(16) NOT NULL,
                    customer_id BINARY(16) NOT NULL,
                    mollie_id VARCHAR(255),
                    mollie_customer_id VARCHAR(255),
                    description VARCHAR(255) NULL,
                    amount FLOAT (10,2) NOT NULL,
                    quantity INT(11) NOT NULL,
                    currency VARCHAR(255) NOT NULL,
                    product_id BINARY(16) NOT NULL,
                    order_id BINARY(16) NOT NULL,
                    sales_channel_id BINARY(16) NOT NULL,
                    next_payment_at DATETIME(3),
                    last_reminded_at DATETIME(3),
                    canceled_at DATETIME(3),
                    created_at DATETIME(3) NOT NULL,
                    updated_at DATETIME(3) NULL,
                    metadata json NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                "
        );

        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.id', 'id', $connection);
        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.customer_id', 'customer_id', $connection);
        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.product_id', 'product_id', $connection);
        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.canceled_at', 'canceled_at', $connection);
        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.next_payment_at', 'next_payment_at', $connection);
        $this->buildIndex('mollie_subscription', 'idx.mollie_subscription.sales_channel_id', 'sales_channel_id', $connection);
    }

    /**
     * @param Connection $connection
     * @return void
     */
    public function updateDestructive(Connection $connection): void
    {
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
