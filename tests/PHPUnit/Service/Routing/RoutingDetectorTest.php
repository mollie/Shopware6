<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\Routing;

use Kiener\MolliePayments\Service\Router\RoutingDetector;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RoutingDetectorTest extends TestCase
{
    /**
     * This test verifies that our router is correctly
     * used and its generated URL is being returned correctly.
     */
    #[TestWith([true, 'frontend.mollie.webhook'])]
    #[TestWith([false, 'api.mollie.webhook'])]
    #[TestWith([false, 'some.route'])]
    #[TestWith([false, ''])]
    public function testIsStorefrontWebhookRoute(bool $expected, string $routeId): void
    {
        $request = new Request();
        $request->attributes->set('_route', $routeId);

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $routingDetector = new RoutingDetector($requestStack);

        $isRoute = $routingDetector->isStorefrontWebhookRoute();

        $this->assertEquals($expected, $isRoute);
    }

    /**
     * This test verifies that our router is correctly
     * used and its generated URL is being returned correctly.
     */
    #[TestWith([true, 'api.mollie.webhook'])]
    #[TestWith([false, 'frontend.mollie.webhook'])]
    #[TestWith([false, 'some.route'])]
    #[TestWith([false, ''])]
    public function testIsApiWebhookRoute(bool $expected, string $routeId): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'api.mollie.webhook');

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $routingDetector = new RoutingDetector($requestStack);

        $isRoute = $routingDetector->isApiWebhookRoute();

        $this->assertEquals(true, $isRoute);
    }
}
