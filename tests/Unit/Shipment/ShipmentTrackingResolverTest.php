<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\Tracking;
use Mollie\Shopware\Component\Shipment\ShipmentTrackingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ShipmentTrackingResolver::class)]
final class ShipmentTrackingResolverTest extends TestCase
{
    private ShipmentTrackingResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ShipmentTrackingResolver();
    }

    public function testExplicitRequestTrackingTakesPrecedence(): void
    {
        $request = new Request([], [
            'trackingCarrier' => 'DHL',
            'trackingCode' => 'ABC123',
            'trackingUrl' => 'https://dhl.example/ABC123',
        ]);

        $tracking = $this->resolver->resolve($request, new OrderDeliveryCollection(), []);

        self::assertInstanceOf(Tracking::class, $tracking);
        self::assertSame('DHL', $tracking->getCarrier());
        self::assertSame('ABC123', $tracking->getCode());
        self::assertSame('https://dhl.example/ABC123', $tracking->getUrl());
    }

    public function testReturnsNullWhenNoCarrierAndNoMatchingDeliveries(): void
    {
        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection(), ['line-1']);

        self::assertNull($tracking);
    }
}
