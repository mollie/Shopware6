<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription\Response;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{subscriptions:array<SubscriptionEntity>}>>
 */
class SubscriptionsListResponse extends StoreApiResponse
{
    /**
     * @param array<SubscriptionEntity> $subscriptions
     */
    public function __construct(array $subscriptions)
    {
        $object = new ArrayStruct(
            [
                'subscriptions' => $subscriptions,
            ],
            'mollie_payments_subscriptions_list'
        );

        parent::__construct($object);
    }
}
