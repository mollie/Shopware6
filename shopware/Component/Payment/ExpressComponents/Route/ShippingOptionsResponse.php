<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<\Shopware\Core\Framework\Struct\ArrayStruct<array{shippingOptions: array<int, array<string, mixed>>}>>
 */
final class ShippingOptionsResponse extends StoreApiResponse
{
    public function __construct(private ShippingOptionCollection $shippingOptions)
    {
        parent::__construct(new ArrayStruct(
            [
                'shippingOptions' => $shippingOptions->toArray(),
            ],
            'express_components_shipping_options_response',
        ));
    }

    public function getShippingOptions(): ShippingOptionCollection
    {
        return $this->shippingOptions;
    }
}
