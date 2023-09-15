<?php

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class RevokeMandateResponse extends StoreApiResponse
{
    /**
     * @var SuccessStruct
     */
    protected $object;

    public function __construct()
    {
        $this->object = new SuccessStruct(
            true,
            'mollie_payments_mandate_revoke'
        );

        parent::__construct($this->object);
    }
}
