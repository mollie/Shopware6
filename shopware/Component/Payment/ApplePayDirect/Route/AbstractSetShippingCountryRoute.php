<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractSetShippingCountryRoute
{
    abstract public function getDecorated(): self;

    abstract public function setShippingCountry(Request $request, SalesChannelContext $salesChannelContext): SetShippingCountryResponse;
}
