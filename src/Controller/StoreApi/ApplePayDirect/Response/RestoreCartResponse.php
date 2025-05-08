<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class RestoreCartResponse extends StoreApiResponse
{
    public function __construct(bool $success)
    {
        $object = new SuccessStruct(
            $success,
            'mollie_payments_applepay_direct_cart_restored'
        );

        parent::__construct($object);
    }
}
