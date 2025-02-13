<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Checkout\Cart;

use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartService;
use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ExpressCartItemAddRoute extends AbstractCartItemAddRoute
{
    /**
     * @var AbstractCartItemAddRoute
     */
    private $cartItemAddRoute;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(AbstractCartItemAddRoute $cartItemAddRoute, ContainerInterface $container)
    {
        $this->cartItemAddRoute = $cartItemAddRoute;
        $this->container = $container;
    }

    public function getDecorated(): AbstractCartItemAddRoute
    {
        return $this->cartItemAddRoute;
    }

    /**
     * @param Request $request
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @param ?array<mixed> $items
     * @return CartResponse
     */
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        //we have to create a new request from global variables, because the request is not set here in the route
        $tempRequest = Request::createFromGlobals();

        $isExpressCheckout = (bool)$tempRequest->get('isExpressCheckout', false);

        if ($isExpressCheckout === false) {
            return $this->getDecorated()->add($request, $cart, $context, $items);
        }

        //Shopware 6.4 have circular injection, we have to use container
        $cartBackupService = $this->container->get(CartBackupService::class);
        $cartService = $this->container->get(CartService::class);

        # add product somehow happens twice, so dont backup our express-cart, only originals
        if (!$cartBackupService->isBackupExisting($context)) {
            $cartBackupService->backupCart($context);
        }

        # clear existing cart and also update it to save it
        $cart->setLineItems(new LineItemCollection());

        $mollieCart = new MollieShopwareCart($cart);

        # we mark the cart as single product express checkout
        # because this helps us to decide whether express checkout is done or
        # a checkout of an existing cart is started (offcanvas, cart...)
        $mollieCart->setSingleProductExpressCheckout(true);

        $cart = $mollieCart->getCart();

        $cartService->updateCart($cart);

        return $this->getDecorated()->add($request, $cart, $context, $items);
    }
}
