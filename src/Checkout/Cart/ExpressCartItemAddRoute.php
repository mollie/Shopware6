<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Checkout\Cart;

use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartServiceInterface;
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
     * @var CartBackupService
     */

    private $cartBackupService;
    /**
     * @var CartServiceInterface
     */
    private $cartService;

    public function __construct(AbstractCartItemAddRoute $cartItemAddRoute, CartBackupService $cartBackupService, CartServiceInterface $cartService)
    {
        $this->cartItemAddRoute = $cartItemAddRoute;
        $this->cartBackupService = $cartBackupService;
        $this->cartService = $cartService;
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
        $request = Request::createFromGlobals();

        $isExpressCheckout = (bool)$request->get('isExpressCheckout', false);
        if ($isExpressCheckout === false) {
            return $this->getDecorated()->add($request, $cart, $context, $items);
        }

        if (!$this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->backupCart($context);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);

        # clear existing cart and also update it to save it
        $cart->setLineItems(new LineItemCollection());
        $this->cartService->updateCart($cart);

        return $this->getDecorated()->add($request, $cart, $context, $items);
    }
}
