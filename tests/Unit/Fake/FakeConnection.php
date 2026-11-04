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

    /** @var list<array<mixed>> */
    private array $executedParameters = [];

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
     * @return list<array<mixed>>
     */
    public function getExecutedParameters(): array
    {
        return $this->executedParameters;
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
     * Answers with the first prepared row, `false` without one - like DBAL for a query that
     * matched nothing.
     *
     * @param array<mixed> $params
     * @param array<mixed> $types
     *
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->fetchedStatements[] = $query;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->rows[0] ?? false;
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
        $this->executedParameters[] = $params;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->affectedRows;
    }
}
