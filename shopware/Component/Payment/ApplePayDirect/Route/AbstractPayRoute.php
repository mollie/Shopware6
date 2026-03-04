<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractPayRoute
{
    abstract public function getDecorated(): self;

    abstract public function pay(Request $request, SalesChannelContext $salesChannelContext): PayResponse;
}
