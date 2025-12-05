<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Utils;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelUtils
{
    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(EntityRepository $salesChannelRepository)
    {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * Return the first sales channel with type "Storefront" or null if non was found.
     */
    public function getStorefrontSalesChannel(): ?SalesChannelEntity
    {
        return $this->getSalesChannelByType(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }

    public function getSalesChannelByType(string $salesChannelType): ?SalesChannelEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('typeId', $salesChannelType))
            ->setLimit(1)
        ;

        $criteria->setTitle(\sprintf('%s::%s()', __CLASS__, __FUNCTION__));

        $salesChannel = $this->salesChannelRepository
            ->search($criteria, Context::createDefaultContext())
            ->first()
        ;

        return $salesChannel instanceof SalesChannelEntity ? $salesChannel : null;
    }
}
