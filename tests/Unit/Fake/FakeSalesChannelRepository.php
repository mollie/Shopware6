<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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
}
