<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface OrderAddressSynchronizerInterface
{
    public function sync(OrderEntity $order, SalesChannelContext $salesChannelContext): void;
}
