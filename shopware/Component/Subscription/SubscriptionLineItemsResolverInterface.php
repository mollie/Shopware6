<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SubscriptionLineItemsResolverInterface
{
    /**
     * Returns the line items relevant for subscription detection on the current
     * checkout surface. When `$orderId` is empty, the active cart of the
     * sales-channel context is consulted; otherwise the persisted order is loaded.
     */
    public function resolveLineItems(string $orderId, SalesChannelContext $salesChannelContext): Collection;
}
