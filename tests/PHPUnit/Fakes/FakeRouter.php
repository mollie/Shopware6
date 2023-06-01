<?php

namespace MolliePayments\Tests\Fakes;

use http\Env\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class FakeRouter implements RouterInterface
{
    /**
     * @var string
     */
    private $url;


    /**
     * FakeRouter constructor.
     * @param string $url
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }


    /**
     * @param string $name
     * @param array $parameters
     * @param int $referenceType
     * @return string
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->url;
    }

    /**
     * @param RequestContext $context
     */
    public function setContext(RequestContext $context)
    {
    }

    /**
     * @return RequestContext|void
     */
    public function getContext(): RequestContext
    {
    }

    /**
     * @return \Symfony\Component\Routing\RouteCollection|void
     */
    public function getRouteCollection()
    {
    }

    /**
     * @param string $pathinfo
     * @return array|void
     */
    public function match($pathinfo): array
    {
    }
}
