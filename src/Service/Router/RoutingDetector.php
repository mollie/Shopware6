<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Router;

use Symfony\Component\HttpFoundation\RequestStack;

class RoutingDetector
{
    /**
     * This is the ID of the webhook route of the storefront.
     */
    private const ROUTE_ID_STOREFRONT_WEBHOOK = 'frontend.mollie.webhook';

    /**
     * This is the ID of the webhook route of the (headless) API.
     */
    private const ROUTE_ID_API_WEBHOOK = 'api.mollie.webhook';

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function isAdminApiRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        $isStoreApi = false;

        if ($request !== null) {
            $isStoreApi = (strpos($request->getPathInfo(), '/api/mollie/') !== false);
        }

        return $isStoreApi;
    }

    public function isStoreApiRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        $isStoreApi = false;

        if ($request !== null) {
            $isStoreApi = (strpos($request->getPathInfo(), '/store-api') === 0);
        }

        return $isStoreApi;
    }

    public function isStorefrontRoute(): bool
    {
        return ! $this->isAdminApiRoute() && ! $this->isStoreApiRoute();
    }

    public function isStorefrontWebhookRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return false;
        }

        $route = (string) $request->get('_route');

        return $route === self::ROUTE_ID_STOREFRONT_WEBHOOK;
    }

    public function isApiWebhookRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return false;
        }

        $route = (string) $request->get('_route');

        return $route === self::ROUTE_ID_API_WEBHOOK;
    }
}
