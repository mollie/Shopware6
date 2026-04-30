<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface SubscriptionGroupCartBuilderInterface
{
    /**
     * Builds a temporary Shopware cart that contains only the order's line items
     * belonging to the requested interval group. Returns `null` when the order has
     * no line items or when the group has no matching products.
     */
    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?string $billingAddressId = null,
        ?string $shippingAddressId = null
    ): ?SubscriptionGroupCart;
}
