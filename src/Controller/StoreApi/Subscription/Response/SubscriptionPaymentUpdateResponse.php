<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SubscriptionPaymentUpdateResponse extends StoreApiResponse
{
    public function __construct(string $checkoutUrl)
    {
        $object = new ArrayStruct(
            [
                'checkoutUrl' => $checkoutUrl,
            ],
            'mollie_payments_subscriptions_payment_update'
        );

        parent::__construct($object);
    }
}
