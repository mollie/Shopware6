<?php

namespace Kiener\MolliePayments\Controller\StoreApi\POS\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class TerminalsResponse extends StoreApiResponse
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
                'terminals' => $terminals,
            ],
            'mollie_payments_pos_terminals'
        );

        parent::__construct($this->object);
    }
}
