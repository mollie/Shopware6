<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes\Repositories;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class FakeOrderLineItemRepository extends EntityRepository
{
    private OrderLineItemCollection $collection;

    public function __construct(OrderLineItemCollection $collection)
    {
        $this->collection = $collection;
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement update() method.
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            OrderLineItemEntity::class,
            $this->collection->count(),
            $this->collection,
            null,
            $criteria,
            $context
        );
    }
}
