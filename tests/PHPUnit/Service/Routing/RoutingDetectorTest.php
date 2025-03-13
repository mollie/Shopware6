<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Routing;

use Kiener\MolliePayments\Service\Router\RoutingDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RoutingDetectorTest extends TestCase
{
    /**
     * This test verifies that our router is correctly
     * used and its generated URL is being returned correctly.
     *
     * @testWith        [true, "frontend.mollie.webhook"]
     *                  [false, "api.mollie.webhook"]
     *                  [false, "some.route"]
     *                  [false, ""]
     */
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
     *
     * @testWith        [true,  "api.mollie.webhook"]
     *                  [false, "frontend.mollie.webhook"]
     *                  [false, "some.route"]
     *                  [false, ""]
     */
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
