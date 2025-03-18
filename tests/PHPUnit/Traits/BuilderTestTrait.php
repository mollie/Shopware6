<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Traits;

use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\Router\RoutingDetector;
use MolliePayments\Tests\Fakes\FakePluginSettings;
use MolliePayments\Tests\Fakes\FakeRouter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait BuilderTestTrait
{
    public function buildRoutingBuilder(TestCase $testCase, string $generatedURL): RoutingBuilder
    {
        $fakeRouter = new FakeRouter($generatedURL);

        $routingDetector = new RoutingDetector(new RequestStack(new Request()));

        return new RoutingBuilder(
            $fakeRouter,
            $routingDetector,
            new FakePluginSettings(''),
            ''
        );
    }
}
