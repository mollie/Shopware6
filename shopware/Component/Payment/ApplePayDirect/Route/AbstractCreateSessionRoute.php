<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractCreateSessionRoute
{
    abstract public function getDecorated(): self;

    abstract public function session(Request $request, SalesChannelContext $salesChannelContext): CreateSessionResponse;
}
