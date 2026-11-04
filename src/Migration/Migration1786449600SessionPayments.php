<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1786449600SessionPayments extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786449600;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS mollie_session_payments (
                    id BINARY(16) NOT NULL,
                    session_id VARCHAR(255) NOT NULL,
                    payment_id VARCHAR(255) NULL,
                    transaction_id BINARY(16) NULL,
                    created_at DATETIME(3) NOT NULL,
                    updated_at DATETIME(3) NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $utils = new MigrationUtils($connection);
        $utils->buildIndex('mollie_session_payments', 'idx.mollie_session_payments.session_id', 'session_id');
        $utils->buildIndex('mollie_session_payments', 'idx.mollie_session_payments.transaction_id', 'transaction_id');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
