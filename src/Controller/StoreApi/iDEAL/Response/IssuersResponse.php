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
     * @param array<mixed> $terminals
     */
    public function __construct(array $terminals)
    {
        $this->object = new ArrayStruct(
            [
                'issuers' => $terminals,
            ],
            'mollie_payments_ideal_issuers'
        );

        parent::__construct($this->object);
    }
}
