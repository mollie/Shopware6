<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingMethod;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class GetShippingMethodsResponse extends StoreApiResponse
{
    /**
     * @param ApplePayShippingMethod[] $shippingMethods
     */
    public function __construct(private array $shippingMethods)
    {
        $object = new ArrayStruct([
            'shippingMethods' => $shippingMethods,
        ],
            'mollie_payments_applepay_direct_shipping_methods');
        parent::__construct($object);
    }

    /**
     * @return ApplePayShippingMethod[]
     */
    public function getShippingMethods(): array
    {
        return $this->shippingMethods;
    }
}
