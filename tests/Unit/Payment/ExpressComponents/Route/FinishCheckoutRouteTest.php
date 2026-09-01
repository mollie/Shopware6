<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutRoute;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeCartCheckoutFinisher;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeOrderCheckoutFinisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mollie redirects the shopper here after the payment inside the express component. Whether the
 * checkout started from a cart or from an existing order is only visible in the url parameters,
 * and the two are different flows.
 */
#[CoversClass(FinishCheckoutRoute::class)]
final class FinishCheckoutRouteTest extends TestCase
{
    private FakeCartCheckoutFinisher $cartCheckoutFinisher;
    private FakeOrderCheckoutFinisher $orderCheckoutFinisher;

    protected function setUp(): void
    {
        $this->cartCheckoutFinisher = new FakeCartCheckoutFinisher();
        $this->orderCheckoutFinisher = new FakeOrderCheckoutFinisher();
    }

    public function testTheCheckoutIsFinishedForTheCartOfTheToken(): void
    {
        $route = $this->route(enabled: true);

        $response = $route->finishCheckout($this->request([FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token']), new FakeSalesChannelContext());

        $this->assertSame('cart-token', $this->cartCheckoutFinisher->getLastCartToken());
        $this->assertSame('ses_cart', $response->getSessionId());
    }

    /**
     * A failed payment sends the shopper to the edit order page, where the order already exists
     * and there is no cart.
     */
    public function testAnOrderIdTakesTheOrderFlowInsteadOfTheCartFlow(): void
    {
        $route = $this->route(enabled: true);

        $response = $route->finishCheckout($this->request([FinishCheckoutRoute::ORDER_ID_PARAMETER => 'order-id']), new FakeSalesChannelContext());

        $this->assertSame('order-id', $this->orderCheckoutFinisher->getLastOrderId());
        $this->assertFalse($this->cartCheckoutFinisher->wasCalled());
        $this->assertSame('ses_order', $response->getSessionId());
    }

    public function testARequestWithoutACartTokenIsRejected(): void
    {
        $route = $this->route(enabled: true);

        $this->expectException(ExpressComponentsException::class);

        $route->finishCheckout($this->request([]), new FakeSalesChannelContext());
    }

    public function testADisabledExpressComponentsCheckoutIsRejected(): void
    {
        $route = $this->route(enabled: false);

        $this->expectException(ExpressComponentsException::class);

        $route->finishCheckout($this->request([FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token']), new FakeSalesChannelContext());
    }

    public function testTheRouteCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->route(enabled: true)->getDecorated();
    }

    private function route(bool $enabled): FinishCheckoutRoute
    {
        return new FinishCheckoutRoute(
            new FakeSettingsService(expressComponentsSettings: new ExpressComponentsSettings($enabled)),
            $this->cartCheckoutFinisher,
            $this->orderCheckoutFinisher
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function request(array $parameters): Request
    {
        return new Request($parameters);
    }
}
