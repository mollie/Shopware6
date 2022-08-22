<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;


use Kiener\MolliePayments\Struct\EnabledStruct;
use Kiener\MolliePayments\Struct\StringStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;


class GetIDResponse extends StoreApiResponse
{

    /**
     * @var StringStruct
     */
    protected $object;


    /**
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->object = new StringStruct(
            $id,
            'mollie_payments_applepay_direct_id'
        );

        parent::__construct($this->object);
    }

}
