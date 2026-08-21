<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractFinishCheckoutRoute
{
    abstract public function getDecorated(): self;

    abstract public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): FinishCheckoutResponse;
}
