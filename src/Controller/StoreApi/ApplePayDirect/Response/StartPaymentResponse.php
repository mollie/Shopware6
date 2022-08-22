<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class StartPaymentResponse extends StoreApiResponse
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
            'mollie_payments_applepay_direct_start_payment'
        );

        parent::__construct($this->object);
    }

}
