<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

interface LineItemAnalyzerInterface
{
    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     */
    public function hasSubscriptionProduct(Collection $lineItems): bool;

    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     *
     * @return null|LineItem|OrderLineItemEntity
     */
    public function getFirstSubscriptionProduct(Collection $lineItems): ?Struct;

    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     *
     * @return array<LineItem|OrderLineItemEntity>
     */
    public function getSubscriptionLineItems(Collection $lineItems): array;

    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     *
     * @return array<string, array<LineItem|OrderLineItemEntity>>
     */
    public function groupSubscriptionLineItemsByInterval(Collection $lineItems): array;

    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     */
    public function hasMixedLineItems(Collection $lineItems): bool;
}
