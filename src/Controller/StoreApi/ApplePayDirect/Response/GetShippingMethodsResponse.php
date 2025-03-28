<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\ShippingMethodsStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class GetShippingMethodsResponse extends StoreApiResponse
{
    /**
     * @param array<mixed> $shippingMethods
     */
    public function __construct(array $shippingMethods)
    {
        $object = new ShippingMethodsStruct(
            $shippingMethods,
            'mollie_payments_applepay_direct_shipping_methods'
        );

        parent::__construct($object);
    }
}
