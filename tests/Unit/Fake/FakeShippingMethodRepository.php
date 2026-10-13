<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final class FakeShippingMethodRepository extends EntityRepository
{
    private const SALES_CHANNEL_FIELD = 'salesChannels.id';

    /** @param array<string, list<string>> $salesChannelIdsByShippingMethodId */
    public function __construct(private readonly array $salesChannelIdsByShippingMethodId = [])
    {
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $salesChannelId = $this->getSalesChannelId($criteria);

        $data = [];
        foreach ($criteria->getIds() as $shippingMethodId) {
            $salesChannelIds = $this->salesChannelIdsByShippingMethodId[$shippingMethodId] ?? [];
            if (! in_array($salesChannelId, $salesChannelIds, true)) {
                continue;
            }
            $data[] = ['primaryKey' => $shippingMethodId, 'data' => []];
        }

        return new IdSearchResult(count($data), $data, $criteria, $context);
    }

    private function getSalesChannelId(Criteria $criteria): string
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === self::SALES_CHANNEL_FIELD) {
                return (string) $filter->getValue();
            }
        }

        return '';
    }
}
