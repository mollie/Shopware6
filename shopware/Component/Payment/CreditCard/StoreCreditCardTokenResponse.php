<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\CreditCard;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class StoreCreditCardTokenResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new ArrayStruct(
            [
                'success' => true,
                'message' => 'Using deprecated route, please provide "creditCardToken" in request body for payment'
            ],
            'mollie_payments_creditcard_token_stored'
        );
        parent::__construct($object);
    }
}
