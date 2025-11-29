<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes\Repositories;

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

    public function __construct(OrderEntity $order)
    {
        $this->order = $order;
    }

    public function getCriteriaSearch(): Criteria
    {
        return $this->criteriaSearch;
    }

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

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $this->criteriaSearchIDs = $criteria;

        return new IdSearchResult(
            1,
            [
                [
                    'primaryKey' => $this->order->getId(),
                    'data' => [],
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
