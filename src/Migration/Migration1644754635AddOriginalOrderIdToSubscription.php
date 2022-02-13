<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1644754635AddOriginalOrderIdToSubscription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1644754635;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
ALTER TABLE `mollie_subscription_to_product`
ADD COLUMN `original_order_id` BINARY(16) NOT NULL AFTER `product_id`;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
