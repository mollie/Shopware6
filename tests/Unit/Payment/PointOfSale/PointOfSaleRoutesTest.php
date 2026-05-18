<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PointOfSale;

use Mollie\Shopware\Component\Payment\PointOfSale\Route\ListTerminalsResponse;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\ListTerminalsRoute;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\StoreTerminalResponse;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\StoreTerminalRoute;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ListTerminalsRoute::class)]
#[CoversClass(StoreTerminalRoute::class)]
#[CoversClass(StoreTerminalResponse::class)]
final class PointOfSaleRoutesTest extends TestCase
{
    private FakeSalesChannelContext $context;

    public function setUp(): void
    {
        $this->context = new FakeSalesChannelContext('sc-1', 'token-1');
    }

    public function testListTerminalsIsSuccessful(): void
    {
        $route = new ListTerminalsRoute(
            new FakeGateway(),
            new NullLogger(),
        );

        $response = $route->list($this->context);

        $this->assertInstanceOf(ListTerminalsResponse::class, $response);
    }

    public function testStoreTerminalIsSuccessful(): void
    {
        $route = new StoreTerminalRoute(
            new NullLogger(),
        );

        $response = $route->storeTerminal('cust-1', 'terminal-1', $this->context);

        $this->assertInstanceOf(StoreTerminalResponse::class, $response);
    }
}
