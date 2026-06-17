<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{subscriptions: array<SubscriptionEntity>}>>
 */
final class SubscriptionsListResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    private EntitySearchResult $entitySearchResult;

    /**
     * @param EntitySearchResult<SubscriptionCollection<SubscriptionEntity>> $entitySearchResult
     */
    public function __construct(EntitySearchResult $entitySearchResult)
    {
        $this->entitySearchResult = $entitySearchResult;

        parent::__construct(new ArrayStruct(
            ['subscriptions' => $entitySearchResult->getEntities()->getFlatList()],
            'mollie_payments_subscriptions_list'
        ));
    }

    /**
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    public function getEntitySearchResult(): EntitySearchResult
    {
        return $this->entitySearchResult;
    }
}
