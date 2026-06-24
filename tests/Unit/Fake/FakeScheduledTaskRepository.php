<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

final class FakeScheduledTaskRepository extends EntityRepository
{
    private ScheduledTaskCollection $collection;

    public function __construct()
    {
        $this->collection = new ScheduledTaskCollection();
    }

    public function add(ScheduledTaskEntity $task): void
    {
        $this->collection->add($task);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(ScheduledTaskEntity::class, $this->collection->count(), $this->collection, null, $criteria, $context);
    }
}
