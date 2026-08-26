<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * Records what an indexer asks to iterate over. The real factory resolves the definition through the
 * DAL registry and builds a query against the database, which a unit test has neither of, so the
 * parent constructor is deliberately not called.
 */
final class FakeIteratorFactory extends IteratorFactory
{
    /** @var list<array{definition: string, limit: int}> */
    private array $createdIterators = [];

    public function __construct()
    {
    }

    public function createIterator(string|EntityDefinition $definition, ?array $lastId = null, int $limit = 50, ?string $versionId = null): IterableQuery
    {
        $this->createdIterators[] = [
            'definition' => $definition instanceof EntityDefinition ? $definition->getEntityName() : $definition,
            'limit' => $limit,
        ];

        return new FakeIterableQuery();
    }

    /**
     * @return list<array{definition: string, limit: int}>
     */
    public function getCreatedIterators(): array
    {
        return $this->createdIterators;
    }
}
