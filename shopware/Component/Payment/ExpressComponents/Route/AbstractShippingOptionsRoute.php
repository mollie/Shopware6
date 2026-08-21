<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractShippingOptionsRoute
{
    abstract public function getDecorated(): self;

    abstract public function shippingOptions(string $salesChannelId, string $cartToken, Request $request): ShippingOptionsResponse;
}
