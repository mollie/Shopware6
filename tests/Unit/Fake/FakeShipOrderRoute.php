<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Component\Shipment\Route\AbstractShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class FakeShipOrderRoute extends AbstractShipOrderRoute
{
    private ?Request $lastRequest = null;

    private string $mollieId = 'cap_fake';

    public function withMollieId(string $mollieId): void
    {
        $this->mollieId = $mollieId;
    }

    public function getLastRequest(): Request
    {
        if ($this->lastRequest === null) {
            throw new \RuntimeException('FakeShipOrderRoute::ship has not been called yet.');
        }

        return $this->lastRequest;
    }

    public function getDecorated(): AbstractShipOrderRoute
    {
        throw new \RuntimeException('FakeShipOrderRoute is not decorated.');
    }

    public function ship(Request $request, Context $context): ShipOrderResponse
    {
        $this->lastRequest = $request;

        return new ShipOrderResponse($this->mollieId, 'fakeshopwareorderid', []);
    }
}
