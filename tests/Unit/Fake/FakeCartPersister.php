<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartPersister extends AbstractCartPersister
{
    /** @var list<Cart> */
    private array $savedCarts = [];

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

    public function save(Cart $cart, SalesChannelContext $context): void
    {
        $this->savedCarts[] = $cart;
    }

    public function getDecorated(): AbstractCartPersister
    {
        throw new \LogicException('FakeCartPersister::getDecorated() is not part of the fake.');
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        throw new \LogicException('FakeCartPersister::load() is not part of the fake.');
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
    }

    public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void
    {
    }
}
