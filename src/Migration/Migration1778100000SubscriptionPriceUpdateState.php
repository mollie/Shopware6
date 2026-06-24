<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1778100000SubscriptionPriceUpdateState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1778100000;
    }

    public function update(Connection $connection): void
    {
        $utils = new MigrationUtils($connection);

        if (! $utils->columnExists('mollie_subscription', 'price_update_state')) {
            $connection->executeStatement(
                "ALTER TABLE `mollie_subscription`
                    ADD COLUMN `price_update_state` VARCHAR(16) NOT NULL DEFAULT 'none' AFTER `canceled_at`"
            );
        }

        if (! $utils->columnExists('mollie_subscription', 'next_notified_price')) {
            $connection->executeStatement(
                'ALTER TABLE `mollie_subscription`
                    ADD COLUMN `next_notified_price` DECIMAL(10,2) NULL AFTER `price_update_state`'
            );
        }

        if (! $utils->columnExists('mollie_subscription', 'notified_at')) {
            $connection->executeStatement(
                'ALTER TABLE `mollie_subscription`
                    ADD COLUMN `notified_at` DATETIME(3) NULL AFTER `next_notified_price`'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
