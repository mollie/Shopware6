<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Plugin\Fake;

use Shopware\Core\Framework\Migration\MigrationCollection;

/**
 * The real collection runs the migration steps against the database. Here it only records that it
 * was asked to run, which is what the plugin lifecycle is responsible for.
 */
final class FakeMigrationCollection extends MigrationCollection
{
    private int $migrateInPlaceCount = 0;

    public function __construct()
    {
    }

    public function migrateInPlace(?int $until = null, ?int $limit = null): array
    {
        ++$this->migrateInPlaceCount;

        return [];
    }

    public function getMigrateInPlaceCount(): int
    {
        return $this->migrateInPlaceCount;
    }
}
