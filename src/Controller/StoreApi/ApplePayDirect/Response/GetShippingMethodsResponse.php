<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\ShippingMethodsStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class GetShippingMethodsResponse extends StoreApiResponse
{
    /**
     * @var ShippingMethodsStruct
     */
    protected $object;


    /**
     * @param array<mixed> $shippingMethods
     */
    public function __construct(array $shippingMethods)
    {
        $this->object = new ShippingMethodsStruct(
            $shippingMethods,
            'mollie_payments_applepay_direct_shipping_methods'
        );

        parent::__construct($this->object);
    }
}
