<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait SalesChannelTrait
{
    private function getSalesChannelId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type.name', 'Storefront'));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(1);

        /** @var EntityRepository<SalesChannelCollection<SalesChannelEntity>> $salesChannelRepository */
        $salesChannelRepository = $this->container->get('sales_channel.repository');
        $searchResult = $salesChannelRepository->searchIds($criteria, $context);

        return (string) $searchResult->firstId();
    }
}