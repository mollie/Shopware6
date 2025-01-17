<?php

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class StoreCardTokenResponse extends StoreApiResponse
{
    /**
     * @var SuccessStruct
     */
    protected $object;


    /**
     * @param bool $success
     */
    public function __construct(bool $success)
    {
        $this->object = new SuccessStruct(
            $success,
            'mollie_payments_creditcard_token_stored'
        );

        parent::__construct($this->object);
    }
}
