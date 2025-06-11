<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\POS\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{terminals:array<mixed>}>>
 */
class TerminalsResponse extends StoreApiResponse
{
    /**
     * @param array<mixed> $terminals
     */
    public function __construct(array $terminals)
    {
        $object = new ArrayStruct(
            [
                'terminals' => $terminals,
            ],
            'mollie_payments_pos_terminals'
        );

        parent::__construct($object);
    }
}
