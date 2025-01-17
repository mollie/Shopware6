<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SubscriptionsListResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;


    /**
     * @param array<mixed> $subscriptions
     */
    public function __construct(array $subscriptions)
    {
        $this->object = new ArrayStruct(
            [
                'subscriptions' => $subscriptions,
            ],
            'mollie_payments_subscriptions_list'
        );

        parent::__construct($this->object);
    }
}
