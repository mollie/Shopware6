<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{success:bool,subscriptionId:string}>>
 */
final class UpdatePaymentMethodConfirmedResponse extends StoreApiResponse
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct(new ArrayStruct(
            [
                'success' => true,
                'subscriptionId' => $subscriptionId,
            ],
            'mollie_payments_subscription_payment_update_confirm'
        ));
    }
}
