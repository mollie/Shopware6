<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface OrderCheckoutFinisherInterface
{
    public function finish(string $orderId, SalesChannelContext $salesChannelContext): FinishCheckoutResponse;
}
