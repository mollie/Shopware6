<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutRoute;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContextService;
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
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private FakeCartCheckoutFinisher $cartCheckoutFinisher;
    private FakeOrderCheckoutFinisher $orderCheckoutFinisher;
    private FakeSalesChannelContextService $salesChannelContextService;

    protected function setUp(): void
    {
        $this->cartCheckoutFinisher = new FakeCartCheckoutFinisher();
        $this->orderCheckoutFinisher = new FakeOrderCheckoutFinisher();
        $this->salesChannelContextService = new FakeSalesChannelContextService(new FakeSalesChannelContext(self::SALES_CHANNEL_ID));
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

    /**
     * The payment is already done when Mollie redirects, but Shopware's payment handling can still
     * answer with a redirect of its own, and that one wins over the page of the client.
     */
    public function testTheShopperFollowsTheRedirectOfThePaymentHandling(): void
    {
        $response = $this->route(enabled: true)->finish(self::SALES_CHANNEL_ID, $this->request([
            FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token',
            'finishUrl' => 'https://frontend.example/checkout/finish/{orderId}',
        ]));

        $this->assertSame('https://shop.example/finish', $response->getTargetUrl());
    }

    public function testTheShopperLandsOnTheFinishPageOfTheClientWithTheOrderFilledIn(): void
    {
        $this->cartCheckoutFinisher = new FakeCartCheckoutFinisher(new FinishCheckoutResponse('ses_cart', 'context-token', 'order-id', '10000', ''));

        $response = $this->route(enabled: true)->finish(self::SALES_CHANNEL_ID, $this->request([
            FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token',
            'finishUrl' => 'https://frontend.example/checkout/finish/{orderId}',
        ]));

        $this->assertSame('https://frontend.example/checkout/finish/order-id', $response->getTargetUrl());
    }

    /**
     * Mollie's redirect carries no context token, so the cart is found through the token that was
     * handed to the redirect url.
     */
    public function testTheContextIsBuiltFromTheSalesChannelAndTheCartTokenOfTheUrl(): void
    {
        $this->route(enabled: true)->finish(self::SALES_CHANNEL_ID, $this->request([
            FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token',
            'finishUrl' => 'https://frontend.example/checkout/finish/{orderId}',
        ]));

        $parameters = $this->salesChannelContextService->getLastParameters();

        $this->assertSame(self::SALES_CHANNEL_ID, $parameters->getSalesChannelId());
        $this->assertSame('cart-token', $parameters->getToken());
    }

    public function testAFailedCheckoutSendsTheShopperToTheErrorPageOfTheClient(): void
    {
        $response = $this->route(enabled: false)->finish(self::SALES_CHANNEL_ID, $this->request([
            FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token',
            'errorUrl' => 'https://frontend.example/checkout/failed',
        ]));

        $this->assertSame('https://frontend.example/checkout/failed', $response->getTargetUrl());
    }

    /**
     * Without a page to send the shopper to, the failure has to surface instead of turning into a
     * redirect to nowhere.
     */
    public function testAFailedCheckoutWithoutAnErrorPageIsNotSwallowed(): void
    {
        $this->expectException(ExpressComponentsException::class);

        $this->route(enabled: false)->finish(self::SALES_CHANNEL_ID, $this->request([
            FinishCheckoutRoute::CART_TOKEN_PARAMETER => 'cart-token',
        ]));
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
            $this->orderCheckoutFinisher,
            $this->salesChannelContextService,
            new FakeLogger()
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
