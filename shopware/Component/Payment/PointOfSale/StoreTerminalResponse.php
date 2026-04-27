<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PointOfSale;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class StoreTerminalResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new ArrayStruct(
            [
                'success' => true,
                'message' => 'Using deprecated route, please provide "terminalId" in request body for payment'
            ],
            'mollie_payments_pos_terminal_stored'
        );

        parent::__construct($object);
    }
}
