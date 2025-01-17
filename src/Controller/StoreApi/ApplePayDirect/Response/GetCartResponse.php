<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\ApplePayCartStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class GetCartResponse extends StoreApiResponse
{
    /**
     * @var ApplePayCartStruct
     */
    protected $object;


    /**
     * @param array<mixed> $formattedCart
     */
    public function __construct(array $formattedCart)
    {
        $this->object = new ApplePayCartStruct(
            $formattedCart,
            'mollie_payments_applepay_direct_cart'
        );

        parent::__construct($this->object);
    }
}
