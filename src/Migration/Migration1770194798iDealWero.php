<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1770194798iDealWero extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770194798;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
DELETE FROM media WHERE file_name = 'ideal-icon'
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
UPDATE payment_method_translation 
SET name = 'iDEAL | Wero'
WHERE name = 'iDEAL'
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
