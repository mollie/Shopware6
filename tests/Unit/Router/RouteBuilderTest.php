<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Router;

use Mollie\Shopware\Component\Router\RouteBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

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

    private function createRouteBuilder(string $generatedUrl, RequestStack $requestStack, string $appUrl): RouteBuilder
    {
        $router = $this->createRouter($generatedUrl);

        return new RouteBuilder($router, $requestStack, $appUrl);
    }

    private function createRouter(string $generatedUrl): RouterInterface
    {
        return new class($generatedUrl) implements RouterInterface {
            public function __construct(private string $generatedUrl)
            {
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return $this->generatedUrl;
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            public function getRouteCollection(): RouteCollection
            {
                return new RouteCollection();
            }

            public function match(string $pathinfo): array
            {
                return [];
            }
        };
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
