<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class FakeRouter implements RouterInterface
{
    /**
     * @var string
     */
    private $url;

    /**
     * FakeRouter constructor.
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * @param string $name
     * @param array $parameters
     * @param int $referenceType
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->url;
    }

    public function setContext(RequestContext $context): void
    {
    }

    /**
     * @return RequestContext|void
     */
    public function getContext(): RequestContext
    {
    }

    /**
     * @return RouteCollection|void
     */
    public function getRouteCollection(): RouteCollection
    {
    }

    /**
     * @param string $pathinfo
     *
     * @return array|void
     */
    public function match($pathinfo): array
    {
    }
}
