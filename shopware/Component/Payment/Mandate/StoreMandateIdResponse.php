<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class StoreMandateIdResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new ArrayStruct(
            [
                'success' => true,
                'message' => 'Using deprecated route, please provide "mandateId" in request body for payment'
            ],
            'mollie_payments_creditcard_mandate_id_stored'
        );

        parent::__construct($object);
    }
}
