<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{success:bool,subscriptionId:string,checkoutUrl:string}>>
 */
final class UpdatePaymentMethodResponse extends StoreApiResponse
{
    public function __construct(string $subscriptionId, string $checkoutUrl)
    {
        parent::__construct(new ArrayStruct(
            [
                'success' => true,
                'subscriptionId' => $subscriptionId,
                'checkoutUrl' => $checkoutUrl,
            ],
            'mollie_payments_subscription_payment_update_start'
        ));
    }
}
