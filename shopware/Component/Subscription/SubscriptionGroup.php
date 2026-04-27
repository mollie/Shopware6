<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Interval;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

/**
 * Represents one group of subscription line items that share the same renewal
 * interval. Produced by SubscriptionGrouper::groupByInterval().
 *
 * Each group corresponds to one Mollie subscription that will be created after
 * the first payment succeeds.
 */
final class SubscriptionGroup
{
    /** @var list<LineItem|OrderLineItemEntity> */
    private array $lineItems = [];

    public function __construct(private readonly Interval $interval)
    {
    }

    public function addLineItem(LineItem|OrderLineItemEntity $lineItem): void
    {
        $this->lineItems[] = $lineItem;
    }

    public function getInterval(): Interval
    {
        return $this->interval;
    }

    /**
     * @return list<LineItem|OrderLineItemEntity>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }
}
