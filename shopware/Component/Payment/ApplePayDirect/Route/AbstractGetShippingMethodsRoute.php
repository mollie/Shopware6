<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractGetShippingMethodsRoute
{
    abstract public function getDecorated(): self;

    abstract public function methods(Request $request, Cart $cart, SalesChannelContext $context): GetShippingMethodsResponse;
}
