<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartService extends CartService
{
    private Cart $storedCart;

    public function __construct(Cart $cart)
    {
        $this->storedCart = $cart;
    }

    /**
     * The real service recalculates the cart and thereby overwrites the rule ids of the context -
     * on the edit-order page the cart is empty, so they get reset. Callers that need them have to
     * restore them, which they can only be tested for if the fake does the same.
     */
    public function getCart(string $token, SalesChannelContext $context, bool $caching = true, bool $taxed = false): Cart
    {
        $context->setRuleIds([]);

        return $this->storedCart;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $cart;
    }
}
