<?php

namespace Kiener\MolliePayments\Service\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class RoutingDetector
{

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
}
