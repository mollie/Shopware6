<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class FakeSalesChannelRepository extends EntityRepository
{
    public function __construct(private SalesChannelCollection $collection = new SalesChannelCollection())
    {
    }

    public function add(SalesChannelEntity $salesChannel): void
    {
        $this->collection->add($salesChannel);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(SalesChannelEntity::class, $this->collection->count(), $this->collection, null, $criteria, $context);
    }


    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $collection = $this->filterByActive($criteria);

        $data = [];
        foreach ($collection->getIds() as $id) {
            $data[] = ['data' => ['id' => $id], 'primaryKey' => $id];
        }

        return new IdSearchResult(count($data), $data, $criteria, $context);
    }

    private function filterByActive(Criteria $criteria): SalesChannelCollection
    {
        $collection = $this->collection;

        foreach ($criteria->getFilters() as $filter) {
            if (! $filter instanceof EqualsFilter) {
                continue;
            }

            if ($filter->getField() !== 'active') {
                continue;
            }

            $collection = $collection->filter(fn (SalesChannelEntity $salesChannel) => $salesChannel->getActive() === $filter->getValue());
        }

        return $collection;
    }
}
