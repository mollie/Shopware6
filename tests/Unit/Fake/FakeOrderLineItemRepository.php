<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeOrderLineItemRepository extends EntityRepository
{
    private OrderLineItemCollection $collection;

    /** @var list<array<string,mixed>> */
    private array $upsertedPayloads = [];

    /** @var list<Criteria> */
    private array $searchCriteria = [];

    public function __construct()
    {
        $this->collection = new OrderLineItemCollection();
    }

    public function add(OrderLineItemEntity $lineItem): void
    {
        $this->collection->add($lineItem);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $this->searchCriteria[] = $criteria;

        $ids = $criteria->getIds();

        $filtered = new OrderLineItemCollection();
        foreach ($this->collection as $lineItem) {
            if ($ids !== [] && ! in_array($lineItem->getId(), $ids, true)) {
                continue;
            }
            $filtered->add($lineItem);
        }

        return new EntitySearchResult(OrderLineItemDefinition::ENTITY_NAME, $filtered->count(), $filtered, null, $criteria, $context);
    }

    /**
     * @return list<Criteria>
     */
    public function getSearchCriteria(): array
    {
        return $this->searchCriteria;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getUpserts(): array
    {
        return $this->upsertedPayloads;
    }

    /**
     * @return array<string,mixed>
     */
    public function getLastUpsert(): array
    {
        if ($this->upsertedPayloads === []) {
            throw new \RuntimeException('FakeOrderLineItemRepository has no upsert payloads recorded.');
        }

        return $this->upsertedPayloads[array_key_last($this->upsertedPayloads)];
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
}
