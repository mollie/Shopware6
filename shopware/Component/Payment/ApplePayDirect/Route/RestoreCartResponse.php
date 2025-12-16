<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class RestoreCartResponse extends StoreApiResponse
{
    public function __construct(bool $success)
    {
        $object = new ArrayStruct(
            ['success' => $success],
            'mollie_payments_applepay_direct_cart_restored'
        );

        parent::__construct($object);
    }
}
