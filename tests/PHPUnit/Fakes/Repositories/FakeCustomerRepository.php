<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes\Repositories;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class FakeCustomerRepository extends EntityRepository
{
    public array $entitySearchResults;
    public array $entityWrittenContainerEvents;
    public array $data;

    public function __construct(EntityDefinition $definition)
    {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->entitySearchResults[0];
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data = [$data];

        return $this->entityWrittenContainerEvents[0];
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data = [$data];

        return $this->entityWrittenContainerEvents[0];
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->data = [$data];

        return $this->entityWrittenContainerEvents[0];
    }
}
