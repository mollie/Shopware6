<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartService extends CartService
{
    private Cart $storedCart;

    /** @var array<string, Cart> */
    private array $cartsByToken = [];

    /** @var list<Cart> */
    private array $setCarts = [];

    public function __construct(Cart $cart)
    {
        $this->storedCart = $cart;
    }

    /**
     * A shop holds one cart per token. Tests that work with two of them at once - a checkout
     * cart and its backup - put the second one here; everything else keeps getting the cart
     * from the constructor.
     */
    public function addCart(string $token, Cart $cart): void
    {
        $this->cartsByToken[$token] = $cart;
    }

    public function getSetCartCount(): int
    {
        return count($this->setCarts);
    }

    public function getLastSetCart(): Cart
    {
        if ($this->setCarts === []) {
            throw new \RuntimeException('FakeCartService has no cart recorded that was set.');
        }

        return $this->setCarts[array_key_last($this->setCarts)];
    }

    /**
     * The real service recalculates the cart and thereby overwrites the rule ids of the context -
     * on the edit-order page the cart is empty, so they get reset. Callers that need them have to
     * restore them, which they can only be tested for if the fake does the same.
     */
    public function getCart(string $token, SalesChannelContext $context, bool $caching = true, bool $taxed = false): Cart
    {
        $context->setRuleIds([]);

        return $this->cartsByToken[$token] ?? $this->storedCart;
    }

    public function setCart(Cart $cart): void
    {
        $this->setCarts[] = $cart;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $cart;
    }

    public function createNew(string $token): Cart
    {
        return new Cart($token);
    }

    /**
     * Puts the items into the cart without running Shopware's cart processors, which would need
     * the whole pricing stack. The cart the caller gets back is the one they handed in.
     */
    public function add(Cart $cart, LineItem|array $items, SalesChannelContext $context): Cart
    {
        foreach (is_array($items) ? $items : [$items] as $item) {
            $cart->add($item);
        }

        return $cart;
    }
}
