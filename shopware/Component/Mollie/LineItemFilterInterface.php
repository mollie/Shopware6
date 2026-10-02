<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

interface LineItemFilterInterface
{
    /**
     * Decide whether a line item should be part of the Mollie API payload. Order line items are
     * loaded flat, so the order's line items are needed to tell whether an item has children.
     */
    public function isItemAllowed(CartLineItem|OrderLineItemEntity $item, ?OrderLineItemCollection $orderLineItems = null): bool;
}
