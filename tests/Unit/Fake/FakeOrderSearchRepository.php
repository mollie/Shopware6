<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

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
        $orderNumbers = $this->extractOrderNumbers($criteria);

        if ($ids === [] && $orderNumbers === null) {
            return new EntitySearchResult(OrderEntity::class, $this->collection->count(), $this->collection, null, $criteria, $context);
        }

        $filtered = new OrderCollection();
        foreach ($this->collection as $order) {
            if ($ids !== [] && ! in_array($order->getId(), $ids, true)) {
                continue;
            }
            if ($orderNumbers !== null && ! in_array((string) $order->getOrderNumber(), $orderNumbers, true)) {
                continue;
            }
            $filtered->add($order);
        }

        return new EntitySearchResult(OrderEntity::class, $filtered->count(), $filtered, null, $criteria, $context);
    }

    /**
     * @return null|array<int,string> list of order numbers, or null when no orderNumber filter is set
     */
    private function extractOrderNumbers(Criteria $criteria): ?array
    {
        $orderNumbers = null;
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsAnyFilter && $filter->getField() === 'orderNumber') {
                $orderNumbers = array_map('strval', $filter->getValue());
            }
            if ($filter instanceof EqualsFilter && $filter->getField() === 'orderNumber') {
                $orderNumbers = [(string) $filter->getValue()];
            }
        }

        return $orderNumbers;
    }
}
