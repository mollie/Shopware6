<?php

namespace Kiener\MolliePayments\Controller\StoreApi\iDEAL\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class StoreIssuerResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;

    /**
     * @param bool $success
     */
    public function __construct(bool $success)
    {
        $this->object = new ArrayStruct(
            [
                'success' => $success,
            ],
            'mollie_payments_ideal_issuer_stored'
        );

        parent::__construct($this->object);
    }
}
