<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public, operational shipment API for 3rd parties / ERP systems. These routes address orders by
 * Shopware order number and line items by a single "item" identifier (the ShipOrderRoute resolves
 * whether it is a line item id or a product number), translate the request into the internal format
 * and delegate the actual work to the ShipOrderRoute.
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class ShipmentApiRoute
{
    public function __construct(
        #[Autowire(service: ShipOrderRoute::class)]
        private readonly AbstractShipOrderRoute $shipOrderRoute,
    ) {
    }

    /**
     * Ships all remaining (not yet shipped or cancelled) items of an order.
     */
    #[Route(path: '/api/mollie/ship/order', name: 'api.mollie.ship.order', methods: ['POST'])]
    public function shipOrder(Request $request, Context $context): ShipOrderResponse
    {
        $payload = $this->decodePayload($request);
        $orderNumber = (string) ($payload['orderNumber'] ?? '');

        if ($orderNumber === '') {
            throw ShippingException::missingOrderNumber();
        }

        return $this->executeShipment($this->buildDelegateRequest($payload, $orderNumber, []), $context);
    }

    /**
     * Ships a selected set of items of an order, each addressed by its item identifier.
     */
    #[Route(path: '/api/mollie/ship/order/batch', name: 'api.mollie.ship.order.batch', methods: ['POST'])]
    public function shipOrderBatch(Request $request, Context $context): ShipOrderResponse
    {
        $payload = $this->decodePayload($request);
        $orderNumber = (string) ($payload['orderNumber'] ?? '');

        if ($orderNumber === '') {
            throw ShippingException::missingOrderNumber();
        }

        $requestItems = $payload['items'] ?? [];
        if (! is_array($requestItems) || count($requestItems) === 0) {
            throw ShippingException::noLineItems($orderNumber);
        }

        $items = [];
        foreach ($requestItems as $requestItem) {
            $item = (string) ($requestItem['item'] ?? '');
            if ($item === '') {
                throw ShippingException::missingItemIdentifier();
            }

            $items[] = [
                'id' => $item,
                'quantity' => (int) ($requestItem['quantity'] ?? 1),
            ];
        }

        return $this->executeShipment($this->buildDelegateRequest($payload, $orderNumber, $items), $context);
    }

    /**
     * Ships a single item of an order, addressed by its item identifier.
     */
    #[Route(path: '/api/mollie/ship/item', name: 'api.mollie.ship.item', methods: ['POST'])]
    public function shipItem(Request $request, Context $context): ShipOrderResponse
    {
        $payload = $this->decodePayload($request);
        $orderNumber = (string) ($payload['orderNumber'] ?? '');

        if ($orderNumber === '') {
            throw ShippingException::missingOrderNumber();
        }

        $item = (string) ($payload['item'] ?? '');
        if ($item === '') {
            throw ShippingException::missingItemIdentifier();
        }

        $items = [[
            'id' => $item,
            'quantity' => (int) ($payload['quantity'] ?? 1),
        ]];

        return $this->executeShipment($this->buildDelegateRequest($payload, $orderNumber, $items), $context);
    }

    /**
     * Delegates to the central ship route and turns a no-op (nothing left to ship) into an error,
     * so the operational API reports an unsuccessful shipment instead of a silent success.
     */
    private function executeShipment(Request $request, Context $context): ShipOrderResponse
    {
        $response = $this->shipOrderRoute->ship($request, $context);

        if ($response->getObject()->get('mollieId') === '') {
            throw ShippingException::shipmentNotSuccessful();
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array{id: string, quantity: int}> $items
     */
    private function buildDelegateRequest(array $payload, string $orderNumber, array $items): Request
    {
        return new Request([], [
            'orderNumber' => $orderNumber,
            'items' => $items,
            'trackingCarrier' => (string) ($payload['trackingCarrier'] ?? ''),
            'trackingCode' => (string) ($payload['trackingCode'] ?? ''),
            'trackingUrl' => (string) ($payload['trackingUrl'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return [];
        }

        $payload = json_decode($content, true);

        return is_array($payload) ? $payload : [];
    }
}
