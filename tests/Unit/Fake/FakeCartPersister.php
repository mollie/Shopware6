<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartPersister extends AbstractCartPersister
{
    /** @var list<Cart> */
    private array $savedCarts = [];

    /** @var array<string, Cart> */
    private array $storedCarts = [];

    /** @var list<array{oldToken: string, newToken: string}> */
    private array $replacedTokens = [];

    public function getSaveCount(): int
    {
        return count($this->savedCarts);
    }

    public function getLastSavedCart(): Cart
    {
        if ($this->savedCarts === []) {
            throw new \RuntimeException('FakeCartPersister has no saved cart recorded.');
        }

        return $this->savedCarts[array_key_last($this->savedCarts)];
    }

    /**
     * Makes a cart loadable under a token, the way a persisted cart is. Without this the fake
     * behaves like a shop that never saw the token.
     */
    public function addStoredCart(string $token, Cart $cart): void
    {
        $this->storedCarts[$token] = $cart;
    }

    /**
     * @return list<array{oldToken: string, newToken: string}>
     */
    public function getReplacedTokens(): array
    {
        return $this->replacedTokens;
    }

    public function save(Cart $cart, SalesChannelContext $context): void
    {
        $this->savedCarts[] = $cart;
    }

    public function getDecorated(): AbstractCartPersister
    {
        throw new \LogicException('FakeCartPersister::getDecorated() is not part of the fake.');
    }

    /**
     * The real persister throws for an unknown token, which is how a caller finds out that
     * nothing was ever saved under it.
     */
    public function load(string $token, SalesChannelContext $context): Cart
    {
        if (! isset($this->storedCarts[$token])) {
            throw CartException::tokenNotFound($token);
        }

        return $this->storedCarts[$token];
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
    }

    public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void
    {
        $this->replacedTokens[] = ['oldToken' => $oldToken, 'newToken' => $newToken];
    }
}
