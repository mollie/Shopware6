<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\ShippingCallbackAddress;
use Mollie\Shopware\Component\Payment\ExpressComponents\ShippingOptionsResolverInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeShippingOptionsResolver implements ShippingOptionsResolverInterface
{
    private ?ShippingCallbackAddress $lastAddress = null;

    public function __construct(private ShippingOptionCollection $shippingOptions = new ShippingOptionCollection())
    {
    }

    public function getLastAddress(): ShippingCallbackAddress
    {
        if (! $this->lastAddress instanceof ShippingCallbackAddress) {
            throw new \RuntimeException('FakeShippingOptionsResolver was never called.');
        }

        return $this->lastAddress;
    }

    public function resolve(ShippingCallbackAddress $address, SalesChannelContext $salesChannelContext): ShippingOptionCollection
    {
        $this->lastAddress = $address;

        return $this->shippingOptions;
    }
}
