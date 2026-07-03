<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1783000000RefundItemDeliveryId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783000000;
    }

    public function update(Connection $connection): void
    {
        $utils = new MigrationUtils($connection);

        $utils->createColumn('mollie_refund_item', 'order_delivery_id', 'VARCHAR(64)', '', 'order_line_item_version_id');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
