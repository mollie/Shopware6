<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment\Route;

use Mollie\Shopware\Component\Shipment\Route\ShipItemRoute;
use Mollie\Shopware\Unit\Fake\FakeShipOrderRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * The admin ships a single order line through this route. It only reshapes the request into the
 * items list the ship-order route expects; everything else happens there.
 */
#[CoversClass(ShipItemRoute::class)]
final class ShipItemRouteTest extends TestCase
{
    public function testTheItemIsShippedAsASingleLineOfTheOrder(): void
    {
        $shipOrderRoute = new FakeShipOrderRoute();

        (new ShipItemRoute($shipOrderRoute))->ship($this->request([
            'orderId' => 'order-1',
            'itemId' => 'item-1',
            'quantity' => 2,
        ]), Context::createDefaultContext());

        $delegated = $shipOrderRoute->getLastRequest();

        $this->assertSame('order-1', $delegated->get('orderId'));
        $this->assertSame([['id' => 'item-1', 'quantity' => 2]], $delegated->get('items'));
    }

    public function testTheTrackingCodeIsPassedOnSoTheCustomerGetsIt(): void
    {
        $shipOrderRoute = new FakeShipOrderRoute();

        (new ShipItemRoute($shipOrderRoute))->ship($this->request([
            'orderId' => 'order-1',
            'itemId' => 'item-1',
            'quantity' => 1,
            'trackingCode' => 'TRACK-1',
        ]), Context::createDefaultContext());

        $this->assertSame('TRACK-1', $shipOrderRoute->getLastRequest()->get('trackingCode'));
    }

    /**
     * A quantity sent as a string by the admin must reach Mollie as a number, or the line would be
     * shipped with a quantity Mollie rejects.
     */
    public function testAQuantitySentAsTextIsShippedAsANumber(): void
    {
        $shipOrderRoute = new FakeShipOrderRoute();

        (new ShipItemRoute($shipOrderRoute))->ship($this->request([
            'orderId' => 'order-1',
            'itemId' => 'item-1',
            'quantity' => '3',
        ]), Context::createDefaultContext());

        $this->assertSame([['id' => 'item-1', 'quantity' => 3]], $shipOrderRoute->getLastRequest()->get('items'));
    }

    public function testARequestWithoutAnyDataStillReachesTheShipOrderRoute(): void
    {
        $shipOrderRoute = new FakeShipOrderRoute();

        (new ShipItemRoute($shipOrderRoute))->ship($this->request([]), Context::createDefaultContext());

        $delegated = $shipOrderRoute->getLastRequest();

        $this->assertSame('', $delegated->get('orderId'));
        $this->assertSame([['id' => '', 'quantity' => 0]], $delegated->get('items'));
    }

    public function testTheAnswerOfTheShipOrderRouteIsHandedBack(): void
    {
        $shipOrderRoute = new FakeShipOrderRoute();
        $shipOrderRoute->withMollieId('shp_1');

        $response = (new ShipItemRoute($shipOrderRoute))->ship($this->request([
            'orderId' => 'order-1',
            'itemId' => 'item-1',
            'quantity' => 1,
        ]), Context::createDefaultContext());

        $this->assertSame('shp_1', $response->getMollieId());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request([], $payload);
    }
}
