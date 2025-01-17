<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SubscriptionPauseResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;


    /**
     *
     */
    public function __construct()
    {
        $this->object = new ArrayStruct(
            [
                'success' => true,
            ],
            'mollie_payments_subscriptions_pause'
        );

        parent::__construct($this->object);
    }
}
