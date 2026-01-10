<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

final class LineItemAnalyzer
{
    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     */
    public function hasSubscriptionProduct(Collection $lineItems): bool
    {
        return $this->getFirstSubscriptionProduct($lineItems) instanceof Struct;
    }

    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     *
     * @return null|LineItem|OrderLineItemEntity
     */
    public function getFirstSubscriptionProduct(Collection $lineItems): ?Struct
    {
        foreach ($lineItems as $lineItem) {
            /** @var ?Product $extension */
            $extension = $lineItem->getExtension(Mollie::EXTENSION);
            if ($extension instanceof Product && $extension->isSubscription() === true) {
                return $lineItem;
            }
        }

        return null;
    }

    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     */
    public function hasMixedLineItems(Collection $lineItems): bool
    {
        $subscriptions = 0;
        $others = 0;
        /** @var LineItem|OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }
            /** @var ?Product $extension */
            $extension = $lineItem->getExtension(Mollie::EXTENSION);
            if ($extension instanceof Product && $extension->isSubscription() === true) {
                ++$subscriptions;
                continue;
            }

            ++$others;
        }

        return $subscriptions > 1 || $others > 0;
    }
}
