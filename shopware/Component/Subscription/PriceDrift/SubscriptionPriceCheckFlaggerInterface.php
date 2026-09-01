<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\PriceDrift;

interface SubscriptionPriceCheckFlaggerInterface
{
    /**
     * @param string[] $productIds hex ids of products whose price changed
     */
    public function flagByProductIds(array $productIds): int;

    /**
     * @param string[] $shippingMethodIds hex ids of shipping methods whose price changed
     */
    public function flagByShippingMethodIds(array $shippingMethodIds): int;
}
