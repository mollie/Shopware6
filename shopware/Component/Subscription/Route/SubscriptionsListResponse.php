<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>>
 */
final class SubscriptionsListResponse extends StoreApiResponse
{
    /**
     * @param EntitySearchResult<SubscriptionCollection<SubscriptionEntity>> $subscriptions
     */
    public function __construct(EntitySearchResult $subscriptions)
    {
        parent::__construct($subscriptions);
    }
}
