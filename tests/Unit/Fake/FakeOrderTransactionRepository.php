<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeOrderTransactionRepository extends EntityRepository implements OrderTransactionRepositoryInterface
{
    /** @var list<string> */
    private array $matchingIds = [];

    /** @var list<array<string,mixed>> */
    private array $upsertedPayloads = [];

    private ?OrderTransactionEntity $searchResult = null;

    /** @var list<Criteria> */
    private array $searchCriteria = [];

    /** @var list<Criteria> */
    private array $searchIdsCriteria = [];

    /** @var list<string> */
    private array $requestedSalesChannelIds = [];

    /** @var array<string, list<string>> */
    private array $matchingIdsPerSalesChannel = [];

    public function __construct()
    {
    }

    /**
     * The transaction a findById() lookup answers with. Without it the repository behaves like a
     * lookup that found nothing.
     */
    public function withTransaction(OrderTransactionEntity $transaction): void
    {
        $this->searchResult = $transaction;
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $this->searchCriteria[] = $criteria;

        $entities = new OrderTransactionCollection();
        if ($this->searchResult instanceof OrderTransactionEntity) {
            $entities->add($this->searchResult);
        }

        return new EntitySearchResult(
            OrderTransactionDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            $criteria,
            $context
        );
    }

    /**
     * @return list<Criteria>
     */
    public function getSearchCriteria(): array
    {
        return $this->searchCriteria;
    }

    public function setMatchingIds(string ...$ids): void
    {
        $this->matchingIds = array_values($ids);
    }

    public function setMatchingIdsForSalesChannel(string $salesChannelId, string ...$ids): void
    {
        $this->matchingIdsPerSalesChannel[$salesChannelId] = array_values($ids);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getUpserts(): array
    {
        return $this->upsertedPayloads;
    }

    public function findOpenTransactions(string $salesChannelId, Context $context): IdSearchResult
    {
        $this->requestedSalesChannelIds[] = $salesChannelId;

        return $this->buildIdSearchResult($this->matchingIdsPerSalesChannel[$salesChannelId] ?? $this->matchingIds, $context);
    }

    /**
     * @return list<string>
     */
    public function getRequestedSalesChannelIds(): array
    {
        return $this->requestedSalesChannelIds;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $this->searchIdsCriteria[] = $criteria;

        return $this->buildIdSearchResult($this->matchingIds, $context);
    }

    /**
     * @return list<Criteria>
     */
    public function getSearchIdsCriteria(): array
    {
        return $this->searchIdsCriteria;
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

    /**
     * @param list<string> $ids
     */
    private function buildIdSearchResult(array $ids, Context $context): IdSearchResult
    {
        $data = [];
        foreach ($ids as $id) {
            $data[] = ['data' => ['id' => $id], 'primaryKey' => $id];
        }

        return new IdSearchResult(count($data), $data, new Criteria(), $context);
    }
}
