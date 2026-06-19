<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeOrderTransactionRepository extends EntityRepository implements OrderTransactionRepositoryInterface
{
    /** @var list<string> */
    private array $matchingIds = [];

    /** @var list<array<string,mixed>> */
    private array $upsertedPayloads = [];

    public function __construct()
    {
    }

    public function setMatchingIds(string ...$ids): void
    {
        $this->matchingIds = array_values($ids);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getUpserts(): array
    {
        return $this->upsertedPayloads;
    }

    public function findOpenTransactions(?Context $context = null): IdSearchResult
    {
        return $this->buildIdSearchResult($context ?? new Context(new SystemSource()));
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->buildIdSearchResult($context);
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->upsertedPayloads[] = $entry;
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    private function buildIdSearchResult(Context $context): IdSearchResult
    {
        $data = [];
        foreach ($this->matchingIds as $id) {
            $data[] = ['data' => ['id' => $id], 'primaryKey' => $id];
        }

        return new IdSearchResult(count($data), $data, new Criteria(), $context);
    }
}
