<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ShippingOptionsResolverInterface
{
    public function resolve(ShippingCallbackAddress $address, SalesChannelContext $salesChannelContext): ShippingOptionCollection;
}
