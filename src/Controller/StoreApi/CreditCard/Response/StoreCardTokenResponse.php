<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SuccessStruct>
 */
class StoreCardTokenResponse extends StoreApiResponse
{
    public function __construct(bool $success)
    {
        $object = new SuccessStruct(
            $success,
            'mollie_payments_creditcard_token_stored'
        );

        parent::__construct($object);
    }
}
