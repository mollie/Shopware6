<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect\Fake;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractGetCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetCartResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayAmount;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayCart;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingLineItem;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Answers with the shipping costs the real route would have calculated for the shipping method
 * that is selected in the given context - which is what the shipping-methods route reads back.
 */
final class FakeGetCartRoute extends AbstractGetCartRoute
{
    /**
     * @param array<string, float> $shippingCostsByShippingMethodId
     */
    public function __construct(private readonly array $shippingCostsByShippingMethodId = [])
    {
    }

    public function getDecorated(): AbstractGetCartRoute
    {
        return $this;
    }

    public function cart(Request $request, SalesChannelContext $salesChannelContext): GetCartResponse
    {
        $applePayCart = new ApplePayCart('Fake Sales Channel', new ApplePayAmount(0.0));

        $shippingMethodId = $salesChannelContext->getShippingMethod()->getId();
        $shippingCosts = $this->shippingCostsByShippingMethodId[$shippingMethodId] ?? 0.0;
        $applePayCart->addItem(new ApplePayShippingLineItem('Shipping', new ApplePayAmount($shippingCosts)));

        return new GetCartResponse($applePayCart, new Cart('apple-pay-token'));
    }
}
