<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SubscriptionUpdateShippingResponse extends StoreApiResponse
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
            'mollie_payments_subscriptions_shipping_update'
        );

        parent::__construct($this->object);
    }
}
