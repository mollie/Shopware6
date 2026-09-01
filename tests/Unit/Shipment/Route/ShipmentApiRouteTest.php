<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment\Route;

use Mollie\Shopware\Component\Shipment\Route\ShipmentApiRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderResponse;
use Mollie\Shopware\Component\Shipment\Route\ShippingException;
use Mollie\Shopware\Unit\Fake\FakeShipOrderRoute;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShipmentApiRouteTest extends TestCase
{
    private FakeShipOrderRoute $shipOrderRoute;

    private ShipmentApiRoute $route;

    protected function setUp(): void
    {
        $this->shipOrderRoute = new FakeShipOrderRoute();
        $this->route = new ShipmentApiRoute($this->shipOrderRoute);
    }

    public function testShipItemForwardsSingleItemIdentifier(): void
    {
        $request = $this->jsonRequest([
            'orderNumber' => '10000',
            'item' => 'SW100',
            'quantity' => 2,
        ]);

        $this->route->shipItem($request, Context::createDefaultContext());

        $delegated = $this->shipOrderRoute->getLastRequest();
        static::assertSame('10000', $delegated->get('orderNumber'));
        static::assertSame([['id' => 'SW100', 'quantity' => 2]], $delegated->get('items'));
    }

    public function testShipOrderBatchMapsEachItemIdentifier(): void
    {
        $request = $this->jsonRequest([
            'orderNumber' => '10000',
            'items' => [
                ['item' => 'SW100', 'quantity' => 1],
                ['item' => 'order-line-item-id', 'quantity' => 3],
            ],
        ]);

        $this->route->shipOrderBatch($request, Context::createDefaultContext());

        $delegated = $this->shipOrderRoute->getLastRequest();
        static::assertSame([
            ['id' => 'SW100', 'quantity' => 1],
            ['id' => 'order-line-item-id', 'quantity' => 3],
        ], $delegated->get('items'));
    }

    public function testShipOrderForwardsNoItems(): void
    {
        $request = $this->jsonRequest(['orderNumber' => '10000']);

        $response = $this->route->shipOrder($request, Context::createDefaultContext());

        static::assertInstanceOf(ShipOrderResponse::class, $response);
        static::assertSame([], $this->shipOrderRoute->getLastRequest()->get('items'));
    }

    public function testShipItemThrowsWhenItemIsMissing(): void
    {
        $request = $this->jsonRequest(['orderNumber' => '10000']);

        $this->expectException(ShippingException::class);

        try {
            $this->route->shipItem($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::MISSING_ITEM_IDENTIFIER, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testShipOrderThrowsWhenOrderNumberIsMissing(): void
    {
        $request = $this->jsonRequest(['item' => 'SW100']);

        $this->expectException(ShippingException::class);

        try {
            $this->route->shipOrder($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::MISSING_ORDER_NUMBER, $exception->getErrorCode());

            throw $exception;
        }
    }

    public function testShipReturnsServerErrorWhenNothingWasShipped(): void
    {
        $this->shipOrderRoute->withMollieId('');

        $request = $this->jsonRequest(['orderNumber' => '10000']);

        $this->expectException(ShippingException::class);

        try {
            $this->route->shipOrder($request, Context::createDefaultContext());
        } catch (ShippingException $exception) {
            static::assertSame(ShippingException::SHIPMENT_NOT_SUCCESSFUL, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function testShipReturnsResponseWhenSomethingWasShipped(): void
    {
        $this->shipOrderRoute->withMollieId('cap_real');

        $request = $this->jsonRequest([
            'orderNumber' => '10000',
            'item' => 'SW100',
        ]);

        $response = $this->route->shipItem($request, Context::createDefaultContext());

        static::assertSame('cap_real', $response->getObject()->get('mollieId'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): Request
    {
        return new Request([], [], [], [], [], [], (string) json_encode($payload));
    }
}
