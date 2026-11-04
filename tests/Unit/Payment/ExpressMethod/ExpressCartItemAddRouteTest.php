<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Payment\ExpressMethod\ExpressCartItemAddRoute;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartBackupService;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeCartItemAddRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Symfony\Component\HttpFoundation\Request;

/**
 * An express checkout from the product page empties the shopper's cart before the product is added.
 * Shopware deletes an emptied cart on recalculation and only marks the recalculated cart as no longer
 * persisted, so adding the product to the stale cart would update a row that no longer exists and
 * the product would be lost.
 */
#[CoversClass(ExpressCartItemAddRoute::class)]
final class ExpressCartItemAddRouteTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['isExpressCheckout']);
    }

    public function testExpressCheckoutAddsTheProductToTheRecalculatedCart(): void
    {
        $_GET['isExpressCheckout'] = '1';
        $recalculatedCart = new Cart('cart-token');
        $cartService = new FakeCartService($this->cartWithItem());
        $cartService->setRecalculatedCart($recalculatedCart);
        $route = new ExpressCartItemAddRoute(new FakeCartItemAddRoute(), new FakeCartBackupService(), $cartService);

        $response = $route->add(new Request(), $this->cartWithItem(), new FakeSalesChannelContext(), []);

        $this->assertSame($recalculatedCart, $response->getCart());
    }

    public function testRegularAddToCartKeepsTheShoppersCart(): void
    {
        $cart = $this->cartWithItem();
        $cartService = new FakeCartService($cart);
        $cartService->setRecalculatedCart(new Cart('cart-token'));
        $route = new ExpressCartItemAddRoute(new FakeCartItemAddRoute(), new FakeCartBackupService(), $cartService);

        $response = $route->add(new Request(), $cart, new FakeSalesChannelContext(), []);

        $this->assertSame($cart, $response->getCart());
        $this->assertCount(1, $response->getCart()->getLineItems());
    }

    private function cartWithItem(): Cart
    {
        return CartBuilder::create()
            ->withToken('cart-token')
            ->withLineItem(LineItemBuilder::regular('shirt')->build())
            ->build()
        ;
    }
}
