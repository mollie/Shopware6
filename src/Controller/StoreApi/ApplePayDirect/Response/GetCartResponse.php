<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\ApplePayCartStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ApplePayCartStruct>
 */
class GetCartResponse extends StoreApiResponse
{
    /**
     * @param array<mixed> $formattedCart
     */
    public function __construct(array $formattedCart)
    {
        $object = new ApplePayCartStruct(
            $formattedCart,
            'mollie_payments_applepay_direct_cart'
        );

        parent::__construct($object);
    }
}
