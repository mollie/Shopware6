<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class ShipItemRoute
{
    public function __construct(
        #[Autowire(service: ShipOrderRoute::class)]
        private readonly AbstractShipOrderRoute $shipOrderRoute,
    ) {
    }

    #[Route(path: '/api/_action/mollie/ship/item', name: 'api.action.mollie.ship.item', methods: ['POST'])]
    public function ship(Request $request, Context $context): ShipOrderResponse
    {
        $orderId = (string) $request->get('orderId', '');
        $itemId = (string) $request->get('itemId', '');
        $quantity = (int) $request->get('quantity', 0);
        $trackingCode = (string) $request->get('trackingCode', '');

        $delegateRequest = new Request(
            [],
            [
                'orderId' => $orderId,
                'items' => [['id' => $itemId, 'quantity' => $quantity]],
                'trackingCode' => $trackingCode,
            ]
        );

        return $this->shipOrderRoute->ship($delegateRequest, $context);
    }
}
