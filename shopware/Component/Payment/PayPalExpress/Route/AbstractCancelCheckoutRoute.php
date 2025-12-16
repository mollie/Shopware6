<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCancelCheckoutRoute
{
    abstract public function getDecorated(): self;

    abstract public function cancel(SalesChannelContext $salesChannelContext): CancelCheckoutResponse;
}
