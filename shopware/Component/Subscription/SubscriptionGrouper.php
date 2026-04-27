<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Struct\Collection;

/**
 * Groups subscription line items by their renewal interval.
 *
 * One-off products, promotions, and vouchers are filtered out — only line items
 * whose Mollie product extension has `isSubscription === true` are included.
 *
 * The result is an array keyed by the Mollie interval string (e.g. "1 month",
 * "3 months"). Each entry is a SubscriptionGroup holding the interval and all
 * matching line items. One group → one Mollie subscription to be created after
 * the first payment.
 *
 * This class is a pure function: it has no constructor dependencies and all its
 * logic is exercisable in unit tests without Shopware infrastructure.
 */
final class SubscriptionGrouper
{
    /**
     * @param LineItemCollection|OrderLineItemCollection $lineItems
     * @return array<string, SubscriptionGroup>  keyed by "{value} {unit}" (e.g. "1 month")
     */
    public function groupByInterval(Collection $lineItems): array
    {
        $groups = [];

        foreach ($lineItems as $lineItem) {
            /** @var ?Product $extension */
            $extension = $lineItem->getExtension(Mollie::EXTENSION);

            if (! ($extension instanceof Product) || ! $extension->isSubscription()) {
                continue;
            }

            $interval = $extension->getInterval();
            $key = (string) $interval;

            if (! isset($groups[$key])) {
                $groups[$key] = new SubscriptionGroup($interval);
            }

            $groups[$key]->addLineItem($lineItem);
        }

        return $groups;
    }
}
