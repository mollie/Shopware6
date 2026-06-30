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
 * Shopware order number and line items by product number, translate the request into the internal
 * format and delegate the actual work to the ShipOrderRoute.
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

        return $this->shipOrderRoute->ship($this->buildDelegateRequest($payload, $orderNumber, []), $context);
    }

    /**
     * Ships a selected set of items of an order, addressed by their product numbers.
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
            $identifier = $this->extractItemIdentifier($requestItem);
            if ($identifier === '') {
                throw ShippingException::missingItemIdentifier();
            }

            $items[] = [
                'id' => $identifier,
                'quantity' => (int) ($requestItem['quantity'] ?? 1),
            ];
        }

        return $this->shipOrderRoute->ship($this->buildDelegateRequest($payload, $orderNumber, $items), $context);
    }

    /**
     * Ships a single item of an order, addressed either by its line item id or, when that is unknown,
     * by its product number.
     */
    #[Route(path: '/api/mollie/ship/item', name: 'api.mollie.ship.item', methods: ['POST'])]
    public function shipItem(Request $request, Context $context): ShipOrderResponse
    {
        $payload = $this->decodePayload($request);
        $orderNumber = (string) ($payload['orderNumber'] ?? '');

        if ($orderNumber === '') {
            throw ShippingException::missingOrderNumber();
        }

        $identifier = $this->extractItemIdentifier($payload);
        if ($identifier === '') {
            throw ShippingException::missingItemIdentifier();
        }

        $items = [[
            'id' => $identifier,
            'quantity' => (int) ($payload['quantity'] ?? 1),
        ]];

        return $this->shipOrderRoute->ship($this->buildDelegateRequest($payload, $orderNumber, $items), $context);
    }

    /**
     * Resolves the item identifier from a request fragment. A line item id takes precedence; when it
     * is unknown the product number is used. The ShipOrderRoute resolves either against the order.
     *
     * @param array<string, mixed> $data
     */
    private function extractItemIdentifier(array $data): string
    {
        $lineItemId = (string) ($data['lineItemId'] ?? '');
        if ($lineItemId !== '') {
            return $lineItemId;
        }

        return (string) ($data['productNumber'] ?? '');
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
