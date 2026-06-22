<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Struct\Struct;

interface LineItemFilterInterface
{
    /**
     * Decide whether a line item should be part of the Mollie API payload.
     *
     * @param CartLineItem|OrderLineItemEntity $item
     */
    public function isItemAllowed(Struct $item): bool;
}
