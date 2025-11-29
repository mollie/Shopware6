<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;

class FakeEntityRepository extends EntityRepository
{
    /** @var array<Criteria> */
    public $criteria = [];

    /** @var array<Context> */
    public $context = [];

    /** @var array<string> */
    public $ids = [];

    /** @var array<string> */
    public $newIds = [];

    /** @var array<string> */
    public $names = [];

    /** @var array<string> */
    public $versionIds = [];

    /** @var array<array> */
    public $data = [];

    /** @var array<AggregationResultCollection> */
    public $aggregationResultCollections = [];

    /** @var array<IdSearchResult> */
    public $idSearchResults = [];

    /** @var array<EntityWrittenContainerEvent> */
    public $entityWrittenContainerEvents = [];

    /** @var array<EntitySearchResult> */
    public $entitySearchResults = [];
    /** @var EntityDefinition */
    private $definition;

    public function __construct(EntityDefinition $definition)
    {
        $this->definition = $definition;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        $this->criteria[] = $criteria;
        $this->context[] = $context;

        return array_shift($this->aggregationResultCollections);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $this->criteria[] = $criteria;
        $this->context[] = $context;

        return array_shift($this->idSearchResults);
    }

    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        $this->ids[] = $id;
        $this->context[] = $context;
        $this->newIds[] = $newId;

        return array_shift($this->entityWrittenContainerEvents);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $this->criteria[] = $criteria;
        $this->context[] = $context;

        return array_shift($this->entitySearchResults);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data[] = $data;
        $this->context[] = $context;

        return array_shift($this->entityWrittenContainerEvents);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data[] = $data;
        $this->context[] = $context;

        return array_shift($this->entityWrittenContainerEvents);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data[] = $data;
        $this->context[] = $context;

        return array_shift($this->entityWrittenContainerEvents);
    }

    public function delete(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data[] = $data;
        $this->context[] = $context;

        return array_shift($this->entityWrittenContainerEvents);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        $this->ids[] = $id;
        $this->context[] = $context;
        $this->names[] = $name;
        $this->versionIds[] = $versionId;

        return '';
    }

    public function merge(string $versionId, Context $context): void
    {
    }
}
