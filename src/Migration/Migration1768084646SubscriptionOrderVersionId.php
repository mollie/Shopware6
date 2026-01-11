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
                ADD COLUMN `order_version_id` BINARY(16) NULL AFTER `order_id`,
                DROP INDEX `idx.mollie_subscription.product_id`,
                DROP COLUMN `quantity`,
                DROP COLUMN `product_id`,
                ADD CONSTRAINT `fk.mollie_subscription.order` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
                ';

        $connection->executeQuery($sql);
    }
}
