<?php

namespace MolliePayments\Tests\Fakes\Repositories;

use Kiener\MolliePayments\Components\RefundManager\DAL\Repository\RefundRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

class FakeRefundRepository implements RefundRepositoryInterface
{

    /**
     * @var array<mixed>
     */
    private $receivedCreateData;


    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->receivedCreateData = $data;

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
    }

    /**
     * @param array<mixed> $ids
     * @param Context $context
     * @return EntityWrittenContainerEvent
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
