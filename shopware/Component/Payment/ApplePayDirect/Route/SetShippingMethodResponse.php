<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class SetShippingMethodResponse extends StoreApiResponse
{
    public function __construct(private SalesChannelContext $salesChannelContext)
    {
        $response = new ArrayStruct([
            'salesChannelContextToken' => $this->salesChannelContext->getToken(),
        ],
            'mollie_payments_applepay_direct_shipping_updated'
        );
        parent::__construct($response);
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
