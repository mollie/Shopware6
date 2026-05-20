<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{success:bool,subscriptionId:string,addressId:string,type:string}>>
 */
final class UpdateAddressResponse extends StoreApiResponse
{
    public function __construct(string $subscriptionId, string $addressId, string $type)
    {
        parent::__construct(new ArrayStruct(
            [
                'success' => true,
                'subscriptionId' => $subscriptionId,
                'addressId' => $addressId,
                'type' => $type,
            ],
            'mollie_payments_subscription_address_update'
        ));
    }
}
