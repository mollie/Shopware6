<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect\Fake;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractSetShippingMethodRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\SetShippingMethodResponse;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Hands back a context that actually carries the selected shipping method, so the caller can price
 * the cart against it - the real route does the same through Shopware's context service.
 */
final class FakeSetShippingMethodRoute extends AbstractSetShippingMethodRoute
{
    /** @var list<string> */
    private array $selectedShippingMethodIds = [];

    public function getDecorated(): AbstractSetShippingMethodRoute
    {
        return $this;
    }

    public function setShipping(Request $request, SalesChannelContext $salesChannelContext): SetShippingMethodResponse
    {
        $shippingMethodId = (string) $request->get('identifier');
        $this->selectedShippingMethodIds[] = $shippingMethodId;

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($shippingMethodId);

        $context = new FakeSalesChannelContext($salesChannelContext->getSalesChannelId(), $salesChannelContext->getToken());
        $context->setShippingMethod($shippingMethod);

        return new SetShippingMethodResponse($context);
    }

    /**
     * @return list<string>
     */
    public function getSelectedShippingMethodIds(): array
    {
        return $this->selectedShippingMethodIds;
    }
}
