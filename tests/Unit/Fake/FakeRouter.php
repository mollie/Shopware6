<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class FakeRouter implements RouterInterface
{
    private RequestContext $context;

    /** @var list<array{name: string, parameters: array<string,mixed>}> */
    private array $generatedRoutes = [];

    public function __construct(private string $generatedUrl = '')
    {
        $this->context = new RequestContext();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $this->generatedRoutes[] = ['name' => $name, 'parameters' => $parameters];

        return $this->generatedUrl;
    }

    public function getLastRouteName(): string
    {
        return $this->lastRoute()['name'];
    }

    /**
     * @return array<string,mixed>
     */
    public function getLastParameters(): array
    {
        return $this->lastRoute()['parameters'];
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    public function getRouteCollection(): RouteCollection
    {
        return new RouteCollection();
    }

    public function match(string $pathinfo): array
    {
        return [];
    }

    /**
     * @return array{name: string, parameters: array<string,mixed>}
     */
    private function lastRoute(): array
    {
        if ($this->generatedRoutes === []) {
            throw new \RuntimeException('FakeRouter has not generated a route yet.');
        }

        return $this->generatedRoutes[array_key_last($this->generatedRoutes)];
    }
}
