<?php

namespace MolliePayments\Tests\Fakes\Repository;

use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;


class FakeOrderRepository implements OrderRepositoryInterface
{

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement upsert() method.
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement create() method.
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        // TODO: Implement search() method.
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        // TODO: Implement searchIds() method.
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement update() method.
    }

    public function findByMollieId(string $customerId, string $mollieId, Context $context): EntitySearchResult
    {
        // TODO: Implement findByMollieId() method.
    }

    public function updateOrderLastUpdated(string $orderId, Context $context): void
    {
        // TODO: Implement updateOrderLastUpdated() method.
    }


}