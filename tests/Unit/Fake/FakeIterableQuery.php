<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;

final class FakeIterableQuery implements IterableQuery
{
    /**
     * @param array<string|int, mixed> $ids
     */
    public function __construct(private readonly array $ids = [])
    {
    }

    public function fetch(): array
    {
        return $this->ids;
    }

    public function fetchCount(): int
    {
        return count($this->ids);
    }

    public function getQuery(): QueryBuilder
    {
        throw new \RuntimeException('FakeIterableQuery does not build a query.');
    }

    public function getOffset(): array
    {
        return ['offset' => null];
    }
}
