<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes\Repositories;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

class FakeRefundRepository extends EntityRepository
{
    /**
     * @var array<mixed>
     */
    private $receivedCreateData;

    public function __construct()
    {
    }

    /**
     * @param array<mixed> $data
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->receivedCreateData = $data;

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
    }

    /**
     * @param array<mixed> $ids
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
    }

    /**
     * @return mixed[]
     */
    public function getReceivedCreateData(): array
    {
        return $this->receivedCreateData;
    }
}
