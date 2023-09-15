<?php

namespace Kiener\MolliePayments\Service\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

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


    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }


    /**
     * @return bool
     */
    public function isAdminApiRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        $isStoreApi = false;

        if ($request !== null) {
            $isStoreApi = (strpos($request->getPathInfo(), '/api/mollie/') !== false);
        }

        return $isStoreApi;
    }

    /**
     * @return bool
     */
    public function isStoreApiRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        $isStoreApi = false;

        if ($request !== null) {
            $isStoreApi = (strpos($request->getPathInfo(), '/store-api') === 0);
        }

        return $isStoreApi;
    }

    /**
     * @return bool
     */
    public function isStorefrontRoute(): bool
    {
        return (!$this->isAdminApiRoute() && !$this->isStoreApiRoute());
    }

    /**
     * @return bool
     */
    public function isStorefrontWebhookRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return false;
        }

        $route = (string)$request->get('_route');

        return ($route === self::ROUTE_ID_STOREFRONT_WEBHOOK);
    }

    /**
     * @return bool
     */
    public function isApiWebhookRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return false;
        }

        $route = (string)$request->get('_route');

        return ($route === self::ROUTE_ID_API_WEBHOOK);
    }
}
