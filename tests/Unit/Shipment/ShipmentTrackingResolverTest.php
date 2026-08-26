<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\Tracking;
use Mollie\Shopware\Component\Shipment\ShipmentTrackingResolver;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ShipmentTrackingResolver::class)]
final class ShipmentTrackingResolverTest extends TestCase
{
    private ShipmentTrackingResolver $resolver;

    private OrderEntityBuilder $orderBuilder;

    protected function setUp(): void
    {
        $this->resolver = new ShipmentTrackingResolver();
        $this->orderBuilder = new OrderEntityBuilder();
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

    public function testTrackingIsDerivedFromTheDeliveryAndItsShippingMethod(): void
    {
        $delivery = $this->deliveryWithTracking(['ABC123'], 'https://dhl.example/%s');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertInstanceOf(Tracking::class, $tracking);
        self::assertSame('DHL', $tracking->getCarrier());
        self::assertSame('ABC123', $tracking->getCode());
        self::assertSame('https://dhl.example/ABC123', $tracking->getUrl());
    }

    public function testRequestTrackingCodeIsCombinedWithTheDeliveryCarrier(): void
    {
        $delivery = $this->deliveryWithTracking([], 'https://dhl.example/%s');

        $tracking = $this->resolver->resolve(
            new Request([], ['trackingCode' => 'FROMREQUEST']),
            new OrderDeliveryCollection([$delivery]),
            ['lineitemid']
        );

        self::assertInstanceOf(Tracking::class, $tracking);
        self::assertSame('DHL', $tracking->getCarrier());
        self::assertSame('FROMREQUEST', $tracking->getCode());
    }

    public function testDeliveryWithoutPositionsIsSkipped(): void
    {
        $delivery = $this->orderBuilder->createDeliveryWithoutPositions('deliveryid');
        $delivery->setTrackingCodes(['ABC123']);

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testDeliveryOfAnotherShipmentIsSkipped(): void
    {
        $delivery = $this->deliveryWithTracking(['ABC123'], 'https://dhl.example/%s', 'otherlineitemid');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testDeliveryWithoutShippingMethodIsSkipped(): void
    {
        $delivery = $this->orderBuilder->createDeliveryWithoutShippingMethod('deliveryid', 'lineitemid');
        $delivery->setTrackingCodes(['ABC123']);

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testNoTrackingWithoutACarrierName(): void
    {
        $delivery = $this->deliveryWithTracking(['ABC123'], 'https://dhl.example/%s');
        $delivery->getShippingMethod()?->setName('');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testNoTrackingWithoutATrackingCode(): void
    {
        $delivery = $this->deliveryWithTracking([], 'https://dhl.example/%s');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testNoTrackingWithSeveralTrackingCodes(): void
    {
        // Mollie accepts a single tracking code per shipment, so an ambiguous delivery is sent without one.
        $delivery = $this->deliveryWithTracking(['ABC123', 'DEF456'], 'https://dhl.example/%s');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testNoTrackingForATrackingCodeMollieWouldReject(): void
    {
        $delivery = $this->deliveryWithTracking([str_repeat('A', 100)], 'https://dhl.example/%s');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertNull($tracking);
    }

    public function testShopwarePlaceholderTrackingUrlIsDropped(): void
    {
        // Shopware's own placeholder syntax cannot be filled in with sprintf, so no url is sent.
        $delivery = $this->deliveryWithTracking(['ABC123'], 'https://dhl.example/%s%');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertInstanceOf(Tracking::class, $tracking);
        self::assertSame('', $tracking->getUrl());
    }

    public function testATrackingUrlThatIsNoUrlIsDropped(): void
    {
        $delivery = $this->deliveryWithTracking(['ABC123'], 'not a url %s');

        $tracking = $this->resolver->resolve(new Request(), new OrderDeliveryCollection([$delivery]), ['lineitemid']);

        self::assertInstanceOf(Tracking::class, $tracking);
        self::assertSame('', $tracking->getUrl());
    }

    /**
     * @param list<string> $trackingCodes
     */
    private function deliveryWithTracking(array $trackingCodes, string $trackingUrlTemplate, string $orderLineItemId = 'lineitemid'): OrderDeliveryEntity
    {
        $delivery = $this->orderBuilder->createShippableDelivery('deliveryid', $orderLineItemId);
        $delivery->setTrackingCodes($trackingCodes);
        $delivery->getShippingMethod()?->setTrackingUrl($trackingUrlTemplate);

        return $delivery;
    }
}
