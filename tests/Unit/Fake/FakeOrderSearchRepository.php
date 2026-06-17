<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class FakeOrderSearchRepository extends EntityRepository
{
    private OrderCollection $collection;

    public function __construct()
    {
        $this->collection = new OrderCollection();
    }

    public function add(OrderEntity $order): void
    {
        $this->collection->add($order);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $ids = $criteria->getIds();
        if ($ids === []) {
            return new EntitySearchResult(OrderEntity::class, $this->collection->count(), $this->collection, null, $criteria, $context);
        }

        $filtered = new OrderCollection();
        foreach ($this->collection as $order) {
            if (in_array($order->getId(), $ids, true)) {
                $filtered->add($order);
            }
        }

        return new EntitySearchResult(OrderEntity::class, $filtered->count(), $filtered, null, $criteria, $context);
    }
}
