<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Router;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Unit\Fake\FakeRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(RouteBuilder::class)]
final class RouteBuilderTest extends TestCase
{
    public function testWebhookUrlIsNormalizedToAppUrlOnStoreApiRequest(): void
    {
        $routeBuilder = $this->createRouteBuilder(
            'https://storefront.example:3000/store-api/mollie/webhook/txn-1',
            $this->createStoreApiRequestStack(),
            'https://shop.example'
        );

        $this->assertSame('https://shop.example/store-api/mollie/webhook/txn-1', $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testReturnUrlIsNormalizedToAppUrlOnStoreApiRequest(): void
    {
        $routeBuilder = $this->createRouteBuilder(
            'https://storefront.example:3000/store-api/mollie/payment/txn-1',
            $this->createStoreApiRequestStack(),
            'https://shop.example'
        );

        $this->assertSame('https://shop.example/store-api/mollie/payment/txn-1', $routeBuilder->getReturnUrl('txn-1'));
    }

    public function testAppUrlPortAndQueryArePreserved(): void
    {
        $routeBuilder = $this->createRouteBuilder(
            'https://storefront.example:3000/store-api/mollie/webhook/txn-1?foo=bar',
            $this->createStoreApiRequestStack(),
            'https://shop.example:8000'
        );

        $this->assertSame('https://shop.example:8000/store-api/mollie/webhook/txn-1?foo=bar', $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testSubscriptionWebhookUrlIsNormalizedToAppUrlOnStoreApiRequest(): void
    {
        $routeBuilder = $this->createRouteBuilder(
            'https://storefront.example:3000/store-api/mollie/webhook/subscription/sub-1',
            $this->createStoreApiRequestStack(),
            'https://shop.example'
        );

        $this->assertSame('https://shop.example/store-api/mollie/webhook/subscription/sub-1', $routeBuilder->getSubscriptionWebhookUrl('sub-1'));
    }

    public function testSubscriptionPaymentUpdateWebhookUrlIsNormalizedToAppUrlOnStoreApiRequest(): void
    {
        $routeBuilder = $this->createRouteBuilder(
            'https://storefront.example:3000/store-api/mollie/webhook/subscription/sub-1/mandate/update',
            $this->createStoreApiRequestStack(),
            'https://shop.example'
        );

        $this->assertSame('https://shop.example/store-api/mollie/webhook/subscription/sub-1/mandate/update', $routeBuilder->getSubscriptionPaymentUpdateWebhookUrl('sub-1'));
    }

    public function testFragmentIsPreserved(): void
    {
        $routeBuilder = $this->createRouteBuilder(
            'https://storefront.example:3000/store-api/mollie/webhook/txn-1?foo=bar#section',
            $this->createStoreApiRequestStack(),
            'https://shop.example'
        );

        $this->assertSame('https://shop.example/store-api/mollie/webhook/txn-1?foo=bar#section', $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testStorefrontRequestIsNotNormalized(): void
    {
        $generated = 'https://storefront.example/mollie/webhook/txn-1';
        $routeBuilder = $this->createRouteBuilder(
            $generated,
            $this->createStorefrontRequestStack(),
            'https://shop.example'
        );

        $this->assertSame($generated, $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testEmptyAppUrlIsNoOp(): void
    {
        $generated = 'https://storefront.example:3000/store-api/mollie/webhook/txn-1';
        $routeBuilder = $this->createRouteBuilder(
            $generated,
            $this->createStoreApiRequestStack(),
            ''
        );

        $this->assertSame($generated, $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testLocalhostAppUrlIsNoOp(): void
    {
        $generated = 'https://storefront.example:3000/store-api/mollie/webhook/txn-1';
        $routeBuilder = $this->createRouteBuilder(
            $generated,
            $this->createStoreApiRequestStack(),
            'http://localhost'
        );

        $this->assertSame($generated, $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testWebhookUrlUsesTheStorefrontRouteWithoutARequest(): void
    {
        $router = new FakeRouter('https://shop.example/mollie/webhook/txn-1');
        $routeBuilder = new RouteBuilder($router, new RequestStack(), 'https://shop.example');

        $routeBuilder->getWebhookUrl('txn-1');

        $this->assertSame('frontend.mollie.webhook', $router->getLastRouteName());
    }

    public function testReturnUrlUsesTheStoreApiRouteOnAStoreApiRequest(): void
    {
        $router = new FakeRouter('https://shop.example/store-api/mollie/payment/txn-1');
        $routeBuilder = new RouteBuilder($router, $this->createStoreApiRequestStack(), 'https://shop.example');

        $routeBuilder->getReturnUrl('txn-1');

        $this->assertSame('api.mollie.payment-return', $router->getLastRouteName());
        $this->assertSame(['transactionId' => 'txn-1'], $router->getLastParameters());
    }

    public function testSubscriptionWebhookUrlUsesTheStoreApiRouteOnAStoreApiRequest(): void
    {
        $router = new FakeRouter('https://shop.example/store-api/mollie/webhook/subscription/sub-1');
        $routeBuilder = new RouteBuilder($router, $this->createStoreApiRequestStack(), 'https://shop.example');

        $routeBuilder->getSubscriptionWebhookUrl('sub-1');

        $this->assertSame('api.mollie.webhook.subscription', $router->getLastRouteName());
    }

    public function testUrlWithoutPathIsNotNormalized(): void
    {
        $generated = 'https://storefront.example:3000';
        $routeBuilder = $this->createRouteBuilder($generated, $this->createStoreApiRequestStack(), 'https://shop.example');

        $this->assertSame($generated, $routeBuilder->getWebhookUrl('txn-1'));
    }

    public function testSubscriptionPaymentUpdateReturnUrlPointsAtTheAccountPage(): void
    {
        $router = new FakeRouter('https://storefront.example/account/mollie/subscriptions/sub-1/payment/update-success');
        $routeBuilder = new RouteBuilder($router, $this->createStoreApiRequestStack(), 'https://shop.example');

        // The customer has to land in the storefront, so this url is never rewritten to APP_URL.
        $url = $routeBuilder->getSubscriptionPaymentUpdateReturnUrl('sub-1');

        $this->assertSame('https://storefront.example/account/mollie/subscriptions/sub-1/payment/update-success', $url);
        $this->assertSame('frontend.account.mollie.subscriptions.payment.update-success', $router->getLastRouteName());
        $this->assertSame(['subscriptionId' => 'sub-1'], $router->getLastParameters());
    }

    public function testPaypalExpressRedirectUrlUsesTheStorefrontRoute(): void
    {
        $router = new FakeRouter('https://storefront.example/mollie/paypal-express/finish');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getPaypalExpressRedirectUrl();

        $this->assertSame('frontend.mollie.paypal-express.finish', $router->getLastRouteName());
    }

    public function testPaypalExpressRedirectUrlUsesTheStoreApiRouteOnAStoreApiRequest(): void
    {
        $router = new FakeRouter('https://shop.example/store-api/mollie/paypal-express/finish');
        $routeBuilder = new RouteBuilder($router, $this->createStoreApiRequestStack(), 'https://shop.example');

        $routeBuilder->getPaypalExpressRedirectUrl();

        $this->assertSame('store-api.mollie.paypal-express.checkout.finish', $router->getLastRouteName());
    }

    public function testPaypalExpressCancelUrlUsesTheStorefrontRoute(): void
    {
        $router = new FakeRouter('https://storefront.example/mollie/paypal-express/cancel');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getPaypalExpressCancelUrl();

        $this->assertSame('frontend.mollie.paypal-express.cancel', $router->getLastRouteName());
    }

    public function testPaypalExpressCancelUrlUsesTheStoreApiRouteOnAStoreApiRequest(): void
    {
        $router = new FakeRouter('https://shop.example/store-api/mollie/paypal-express/cancel');
        $routeBuilder = new RouteBuilder($router, $this->createStoreApiRequestStack(), 'https://shop.example');

        $routeBuilder->getPaypalExpressCancelUrl();

        $this->assertSame('store-api.mollie.paypal-express.checkout.cancel', $router->getLastRouteName());
    }

    public function testExpressComponentsRedirectUrlCarriesTheCartToken(): void
    {
        $router = new FakeRouter('https://storefront.example/mollie/express-components/finish');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getExpressComponentsRedirectUrl('cart-token');

        $this->assertSame('frontend.mollie.express-components.finish', $router->getLastRouteName());
        $this->assertSame(['cartToken' => 'cart-token'], $router->getLastParameters());
    }

    public function testExpressComponentsOrderRedirectUrlCarriesTheOrderId(): void
    {
        $router = new FakeRouter('https://shop.example/store-api/mollie/express-components/finish');
        $routeBuilder = new RouteBuilder($router, $this->createStoreApiRequestStack(), 'https://shop.example');

        $routeBuilder->getExpressComponentsOrderRedirectUrl('order-id');

        $this->assertSame('store-api.mollie.express-components.checkout.finish', $router->getLastRouteName());
        $this->assertSame(['orderId' => 'order-id'], $router->getLastParameters());
    }

    public function testExpressComponentsShippingCallbackUrlCarriesSalesChannelAndCartToken(): void
    {
        $router = new FakeRouter('https://shop.example/api/mollie/express-components/shipping-options');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getExpressComponentsShippingCallbackUrl('sales-channel-id', 'cart-token');

        $this->assertSame('api.mollie.express-components.shipping-options', $router->getLastRouteName());
        $this->assertSame(['salesChannelId' => 'sales-channel-id', 'cartToken' => 'cart-token'], $router->getLastParameters());
    }

    public function testCheckoutFinishUrlPointsAtTheOrder(): void
    {
        $router = new FakeRouter('https://storefront.example/checkout/finish?orderId=order-id');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $url = $routeBuilder->getCheckoutFinishUrl('order-id');

        $this->assertSame('https://storefront.example/checkout/finish?orderId=order-id', $url);
        $this->assertSame('frontend.checkout.finish.page', $router->getLastRouteName());
        $this->assertSame(['orderId' => 'order-id'], $router->getLastParameters());
    }

    public function testEditOrderUrlPointsAtTheOrder(): void
    {
        $router = new FakeRouter('https://storefront.example/account/order/edit/order-id');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getEditOrderUrl('order-id');

        $this->assertSame('frontend.account.edit-order.page', $router->getLastRouteName());
        $this->assertSame(['orderId' => 'order-id'], $router->getLastParameters());
    }

    public function testPosCheckoutUrlCarriesTheChangePaymentStateUrl(): void
    {
        $payment = new Payment('tr_123');
        $payment->setChangePaymentStateUrl('https://api.mollie.com/v2/payments/tr_123');

        $router = new FakeRouter('https://storefront.example/mollie/pos/checkout');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getPosCheckoutUrl($payment, 'txn-1', '10000');

        $this->assertSame('frontend.mollie.pos.checkout', $router->getLastRouteName());
        $this->assertSame([
            'transactionId' => 'txn-1',
            'orderNumber' => '10000',
            'paymentId' => 'tr_123',
            'changePaymentStateUrl' => 'https://api.mollie.com/v2/payments/tr_123',
        ], $router->getLastParameters());
    }

    public function testPosCheckoutUrlOmitsAnEmptyChangePaymentStateUrl(): void
    {
        $payment = new Payment('tr_123');
        $payment->setChangePaymentStateUrl('');

        $router = new FakeRouter('https://storefront.example/mollie/pos/checkout');
        $routeBuilder = new RouteBuilder($router, $this->createStorefrontRequestStack(), 'https://shop.example');

        $routeBuilder->getPosCheckoutUrl($payment, 'txn-1', '10000');

        $this->assertArrayNotHasKey('changePaymentStateUrl', $router->getLastParameters());
    }

    private function createRouteBuilder(string $generatedUrl, RequestStack $requestStack, string $appUrl): RouteBuilder
    {
        $router = new FakeRouter($generatedUrl);

        return new RouteBuilder($router, $requestStack, $appUrl);
    }

    private function createStoreApiRequestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://storefront.example:3000/store-api/mollie/webhook/txn-1'));

        return $requestStack;
    }

    private function createStorefrontRequestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://storefront.example/mollie/webhook/txn-1'));

        return $requestStack;
    }
}
