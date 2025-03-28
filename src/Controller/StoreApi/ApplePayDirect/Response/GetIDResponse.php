<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class GetIDResponse extends StoreApiResponse
{
    public function __construct(bool $success, string $id)
    {
        $object = new ArrayStruct(
            [
                'success' => $success,
                'id' => $id,
            ],
            'mollie_payments_applepay_direct_id'
        );

        parent::__construct($object);
    }
}
