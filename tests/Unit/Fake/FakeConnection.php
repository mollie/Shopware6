<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Doctrine\DBAL\Connection;

/**
 * A DBAL connection that answers SELECTs from a prepared list of rows and records the statements
 * it was asked to execute. Constructing the real connection would need a driver and a database,
 * which a unit test has neither of, so the parent constructor is deliberately not called.
 */
final class FakeConnection extends Connection
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var list<string> */
    private array $executedStatements = [];

    /** @var list<string> */
    private array $fetchedStatements = [];

    private int $affectedRows = 0;

    private mixed $singleValue = false;

    private ?\Throwable $failure = null;

    public function __construct()
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withRows(array $rows): void
    {
        $this->rows = $rows;
    }

    public function withAffectedRows(int $affectedRows): void
    {
        $this->affectedRows = $affectedRows;
    }

    /**
     * The value a fetchOne() lookup answers with. DBAL answers `false` when the query matched
     * nothing, which is what an untouched fake reports.
     */
    public function withSingleValue(mixed $singleValue): void
    {
        $this->singleValue = $singleValue;
    }

    public function withFailure(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @return list<string>
     */
    public function getExecutedStatements(): array
    {
        return $this->executedStatements;
    }

    /**
     * @return list<string>
     */
    public function getFetchedStatements(): array
    {
        return $this->fetchedStatements;
    }

    /**
     * @param array<mixed> $params
     * @param array<mixed> $types
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->fetchedStatements[] = $query;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->rows;
    }

    /**
     * @param array<mixed> $params
     * @param array<mixed> $types
     */
    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $this->fetchedStatements[] = $query;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->singleValue;
    }

    /**
     * @param array<mixed> $params
     * @param array<mixed> $types
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->executedStatements[] = $sql;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->affectedRows;
    }
}
