<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\PriceDrift\SubscriptionPriceCheckFlaggerInterface;

final class FakeSubscriptionPriceCheckFlagger implements SubscriptionPriceCheckFlaggerInterface
{
    /**
     * @var array<int,string>
     */
    private array $flaggedProductIds = [];

    /**
     * @var array<int,string>
     */
    private array $flaggedShippingMethodIds = [];

    public function flagByProductIds(array $productIds): int
    {
        $this->flaggedProductIds = array_merge($this->flaggedProductIds, array_values($productIds));

        return count($productIds);
    }

    public function flagByShippingMethodIds(array $shippingMethodIds): int
    {
        $this->flaggedShippingMethodIds = array_merge($this->flaggedShippingMethodIds, array_values($shippingMethodIds));

        return count($shippingMethodIds);
    }

    /**
     * @return array<int,string>
     */
    public function getFlaggedProductIds(): array
    {
        return $this->flaggedProductIds;
    }

    /**
     * @return array<int,string>
     */
    public function getFlaggedShippingMethodIds(): array
    {
        return $this->flaggedShippingMethodIds;
    }
}
