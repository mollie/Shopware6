<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class RevokeMandateResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new SuccessStruct(
            true,
            'mollie_payments_mandate_revoke'
        );

        parent::__construct($object);
    }
}
