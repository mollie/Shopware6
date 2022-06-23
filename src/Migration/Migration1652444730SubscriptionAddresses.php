<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
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
                ");

        $connection->exec('CREATE INDEX `idx.mollie_subscription_address.id` ON mollie_subscription_address (id);');
        $connection->exec('CREATE INDEX `idx.mollie_subscription_address.subscription_id` ON mollie_subscription_address (subscription_id);');

        $connection->exec('ALTER TABLE mollie_subscription ADD billing_address_id binary(16)');
        $connection->exec('ALTER TABLE mollie_subscription ADD shipping_address_id binary(16)');

        $connection->exec('CREATE INDEX `idx.mollie_subscription.billing_address_id` ON mollie_subscription (billing_address_id);');
        $connection->exec('CREATE INDEX `idx.mollie_subscription.shipping_address_id` ON mollie_subscription (shipping_address_id);');
    }

    /**
     * @param Connection $connection
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

}
