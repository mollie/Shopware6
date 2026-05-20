<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface SubscriptionGroupCartBuilderInterface
{
    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?RenewalAddresses $addresses = null
    ): ?SubscriptionGroupCart;
}
