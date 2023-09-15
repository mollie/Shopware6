<?php

namespace Kiener\MolliePayments\Controller\StoreApi\iDEAL\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class IssuersResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;


    /**
     * @param array<mixed> $issuers
     */
    public function __construct(array $issuers)
    {
        $this->object = new ArrayStruct(
            [
                'issuers' => $issuers,
            ],
            'mollie_payments_ideal_issuers'
        );

        parent::__construct($this->object);
    }
}
