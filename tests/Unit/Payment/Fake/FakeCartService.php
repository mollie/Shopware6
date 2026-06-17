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

    public function getCart(string $token, SalesChannelContext $context, bool $caching = true, bool $taxed = false): Cart
    {
        return $this->storedCart;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $cart;
    }
}
