<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stands in for Shopware's own add-to-cart route, which the plugin decorates. It records the items
 * it was handed, so a test can assert what the decorator changed on them before passing them on.
 */
final class FakeCartItemAddRoute extends AbstractCartItemAddRoute
{
    /** @var null|array<LineItem> */
    private ?array $addedItems = null;

    private bool $called = false;

    public function getDecorated(): AbstractCartItemAddRoute
    {
        return $this;
    }

    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        $this->called = true;
        $this->addedItems = $items;

        return new CartResponse($cart);
    }

    /**
     * @return array<LineItem>
     */
    public function getAddedItems(): array
    {
        if ($this->addedItems === null) {
            throw new \RuntimeException('The decorated add-to-cart route was called without items.');
        }

        return $this->addedItems;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }
}
