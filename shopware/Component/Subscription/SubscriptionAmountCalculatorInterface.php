<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface SubscriptionAmountCalculatorInterface
{
    public function calculateGroupAmount(OrderEntity $order, string $intervalKey, Context $context): float;

    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?string $billingAddressId = null,
        ?string $shippingAddressId = null
    ): ?SubscriptionGroupCart;
}
