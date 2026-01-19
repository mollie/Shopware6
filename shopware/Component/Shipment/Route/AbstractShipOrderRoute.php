<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractShipOrderRoute
{
    abstract public function getDecorated(): self;

    abstract public function ship(Request $request, Context $context): ShipOrderResponse;
}
