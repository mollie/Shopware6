<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SubscriptionResumeResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new ArrayStruct(
            [
                'success' => true,
            ],
            'mollie_payments_subscriptions_resume'
        );

        parent::__construct($object);
    }
}
