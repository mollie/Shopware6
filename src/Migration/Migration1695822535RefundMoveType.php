<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1695822535RefundMoveType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695822535;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Connection $connection): void
    {
        $utils = new MigrationUtils($connection);

        $utils->createColumn(
            'mollie_refund',
            'type',
            'VARCHAR(255)',
            'NULL',
            'mollie_refund_id'
        );

        $utils->deleteColumn('mollie_refund_item', 'type');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
