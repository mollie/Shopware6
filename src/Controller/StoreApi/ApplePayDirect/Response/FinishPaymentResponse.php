<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class FinishPaymentResponse extends StoreApiResponse
{

    /**
     * @var ArrayStruct
     */
    protected $object;


    /**
     * @param bool $success
     * @param string $redirectUrl
     * @param string $message
     */
    public function __construct(bool $success, string $redirectUrl, string $message)
    {
        $this->object = new ArrayStruct(
            [
                'success' => $success,
                'url' => $redirectUrl,
                'message' => $message,
            ],
            'mollie_payments_applepay_direct_finish_payment'
        );

        parent::__construct($this->object);
    }

}
