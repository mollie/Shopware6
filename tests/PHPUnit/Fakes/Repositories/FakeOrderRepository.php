<?php

namespace MolliePayments\Tests\Fakes\Repositories;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class FakeOrderRepository extends EntityRepository
{
    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var Criteria
     */
    private $criteriaSearch;

    /**
     * @var Criteria
     */
    private $criteriaSearchIDs;


    /**
     * @param OrderEntity $order
     */
    public function __construct(OrderEntity $order)
    {
        $this->order = $order;
    }

    /**
     * @return Criteria
     */
    public function getCriteriaSearch(): Criteria
    {
        return $this->criteriaSearch;
    }

    /**
     * @return Criteria
     */
    public function getCriteriaSearchIDs(): Criteria
    {
        return $this->criteriaSearchIDs;
    }


    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement upsert() method.
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement create() method.
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $this->criteriaSearch = $criteria;

        return new EntitySearchResult(
            OrderEntity::class,
            1,
            new EntityCollection([$this->order]),
            null,
            $criteria,
            $context
        );
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $this->criteriaSearchIDs = $criteria;

        return new IdSearchResult(
            1,
            [
                [
                    'primaryKey' => $this->order->getId(),
                    'data' => []
                ],
            ],
            $criteria,
            $context
        );
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
