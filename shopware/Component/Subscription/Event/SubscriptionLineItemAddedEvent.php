<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per subscription line item every time the cart is rebuilt.
 * Useful for extensions that need to react to subscription products in the cart
 * (e.g. additional surcharges, custom messaging, analytics).
 */
final class SubscriptionLineItemAddedEvent extends Event
{
    public function __construct(
        private readonly LineItem $lineItem,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
