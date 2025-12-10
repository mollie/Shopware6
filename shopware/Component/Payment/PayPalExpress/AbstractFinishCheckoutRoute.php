<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractFinishCheckoutRoute
{
    abstract public function getDecorated(): AbstractStartCheckoutRoute;

    abstract public function finishCheckout(SalesChannelContext $salesChannelContext): FinishCheckoutResponse;
}
