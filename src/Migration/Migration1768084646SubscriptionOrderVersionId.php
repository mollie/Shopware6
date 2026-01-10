<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1768084646SubscriptionOrderVersionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768084646;
    }

    public function update(Connection $connection): void
    {
        $sql = 'ALTER TABLE `mollie_subscription` 
                ADD COLUMN `order_version_id` BINARY(16) NULL AFTER `order_id`
                ';
        $connection->executeStatement($sql);
    }
}
