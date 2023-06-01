<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Struct\EnabledStruct;
use Kiener\MolliePayments\Struct\StringStruct;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class GetIDResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;


    /**
     * @param bool $success
     * @param string $id
     */
    public function __construct(bool $success, string $id)
    {
        $this->object = new ArrayStruct(
            [
                'success' => $success,
                'id' => $id,
            ],
            'mollie_payments_applepay_direct_id'
        );

        parent::__construct($this->object);
    }
}
