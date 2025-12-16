<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

#[AsDecorator(decorates: CartItemAddRoute::class)]
class ExpressCartItemAddRoute extends AbstractCartItemAddRoute
{
    public function __construct(
        #[AutowireDecorated]
        private AbstractCartItemAddRoute $cartItemAddRoute,
        #[Autowire(service: CartBackupService::class)]
        private AbstractCartBackupService $cartBackupService,
        private CartService $cartService)
    {
    }

    public function getDecorated(): AbstractCartItemAddRoute
    {
        return $this->cartItemAddRoute;
    }

    /**
     * @param ?array<mixed> $items
     */
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        // we have to create a new request from global variables, because the request is not set here in the route
        $tempRequest = Request::createFromGlobals();

        $isExpressCheckout = (bool) $tempRequest->get('isExpressCheckout', false);

        if ($isExpressCheckout === false) {
            return $this->getDecorated()->add($request, $cart, $context, $items);
        }

        // add product somehow happens twice, so dont backup our express-cart, only originals
        if (! $this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->backupCart($context);
        }

        // clear existing cart and also update it to save it
        $cart->setLineItems(new LineItemCollection());

        $this->cartService->recalculate($cart, $context);

        /*
        $mollieCart = new MollieShopwareCart($cart);

        // we mark the cart as single product express checkout
        // because this helps us to decide whether express checkout is done or
        // a checkout of an existing cart is started (off canvas, cart...)
        $mollieCart->setSingleProductExpressCheckout(true);

        $cart = $mollieCart->getCart();

        $cartService->updateCart($cart);*/

        return $this->getDecorated()->add($request, $cart, $context, $items);
    }
}
