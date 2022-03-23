<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;


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
        $this->createMollieSubscriptionToProductTable($connection);
    }

    /**
     * @param Connection $connection
     * @throws Exception
     */
    public function updateDestructive(Connection $connection): void
    {
        $connection->exec("DROP TABLE `mollie_subscription`");
    }

    /**
     * @param Connection $connection
     * @throws Exception
     */
    private function createMollieSubscriptionToProductTable(Connection $connection): void
    {
        $connection->exec(
            "CREATE TABLE IF NOT EXISTS mollie_subscription (
                    id BINARY(16) NOT NULL,
                    mollie_customer_id VARCHAR(255) NOT NULL,
                    mollie_subscription_id VARCHAR(255) NOT NULL,
                    product_id BINARY(16) NOT NULL,
                    sales_channel_id BINARY(16) NOT NULL,
                    description VARCHAR(255) NULL,
                    amount FLOAT (10,3) NOT NULL,
                    currency VARCHAR(255) NOT NULL,
                    original_order_id BINARY(16) NOT NULL,
                    created_at DATETIME(3) NOT NULL,
                    updated_at DATETIME(3) NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
    }

}
