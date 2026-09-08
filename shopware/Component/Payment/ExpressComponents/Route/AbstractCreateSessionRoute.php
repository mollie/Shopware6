<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractCreateSessionRoute
{
    abstract public function getDecorated(): self;

    /**
     * Cart and order are what the storefront already loaded for the page it renders. A store-api
     * caller passes neither and gets the cart of its context token and the order of the orderId.
     */
    abstract public function createSession(Request $request, SalesChannelContext $salesChannelContext, ?Cart $cart = null, ?OrderEntity $order = null): CreateSessionResponse;
}
