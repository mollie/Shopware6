<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class AddProductResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new ArrayStruct(
            ['cart' => []],
            'mollie_payments_applepay_direct_cart'
        );
        parent::__construct($object);
    }
}
