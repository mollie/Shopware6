<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1786276800BackfillSubscriptionOrderVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786276800;
    }

    /**
     * Subscriptions created before the order_version_id column was introduced keep a NULL version id,
     * which breaks the join to the order during renewal. Read the actual version from the order table
     * so the (order_id, order_version_id) pair always satisfies the foreign key; orphaned rows without
     * a matching order are left untouched.
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `mollie_subscription` ms
             SET ms.order_version_id = (
                 SELECT o.version_id FROM `order` o WHERE o.id = ms.order_id LIMIT 1
             )
             WHERE ms.order_version_id IS NULL
               AND EXISTS (SELECT 1 FROM `order` o WHERE o.id = ms.order_id)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
