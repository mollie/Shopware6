<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MigrationUtils
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws Exception
     */
    public function createColumn(string $table, string $column, string $type, string $default, string $after): void
    {
        $colQuery = $this->columnExists($table, $column);

        // only create if not yet existing
        if ($colQuery === false) {
            $sql = 'ALTER TABLE ' . $table . ' ADD ' . $column . ' ' . $type . ' NULL ';

            if (! empty($default)) {
                $sql .= ' DEFAULT ' . $default;
            }

            if (! empty($after)) {
                $sql .= ' AFTER `' . $after . '`';
            }

            $this->connection->exec($sql);
        }
    }

    /**
     * @throws Exception
     */
    public function deleteColumn(string $table, string $column): void
    {
        $colQuery = $this->columnExists($table, $column);

        // only delete if existing
        if ($colQuery !== false) {
            $sql = 'ALTER TABLE ' . $table . ' DROP ' . $column;
            $this->connection->exec($sql);
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->executeQuery('SHOW COLUMNS FROM ' . $table . " LIKE '" . $column . "'")->fetchOne();
    }

    /**
     * @param array<mixed> $columns
     *
     * @throws Exception
     */
    public function addKey(string $table, string $keyName, array $columns): void
    {
        $isExisting = $this->isIndexExisting($table, $keyName);

        if ($isExisting) {
            return;
        }

        $columnsString = implode(',', $columns);

        $sql = 'ALTER TABLE `' . $table . '` ADD KEY `' . $keyName . '` (' . $columnsString . ')';

        $this->connection->exec($sql);
    }

    /**
     * @throws Exception
     */
    public function addConstraint(string $tableName, string $constraintName, string $sqlContent): void
    {
        $isExisting = $this->isIndexExisting($tableName, $constraintName);

        if ($isExisting) {
            return;
        }

        $sql = 'ALTER TABLE `' . $tableName . '`
                ADD CONSTRAINT `' . $constraintName . '` 
                ' . $sqlContent;

        $this->connection->exec($sql);
    }

    /**
     * @throws Exception
     */
    public function buildIndex(string $table, string $indexName, string $targetField): void
    {
        $isExisting = $this->isIndexExisting($table, $indexName);

        if ($isExisting) {
            return;
        }

        $this->connection->exec('CREATE INDEX `' . $indexName . '` ON ' . $table . ' (' . $targetField . ');');
    }

    /**
     * @throws Exception
     *
     * @return bool
     */
    private function isIndexExisting(string $table, string $indexName)
    {
        $indexExistsCheck = $this->connection->executeQuery("
            SELECT COUNT(1) foundCount 
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE() and table_name = '" . $table . "' and index_name = '" . $indexName . "';
        ")->fetch();

        return (int) $indexExistsCheck['foundCount'] >= 1;
    }
}
