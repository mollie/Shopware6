<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{success: bool, subscriptionId: string, action: string}>>
 */
final class ChangeStateResponse extends StoreApiResponse
{
    public function __construct(string $subscriptionId, string $action)
    {
        parent::__construct(new ArrayStruct(
            [
                'success' => true,
                'subscriptionId' => $subscriptionId,
                'action' => $action,
            ],
            'mollie_payments_subscription_change_state'
        ));
    }
}
