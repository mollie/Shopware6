<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Subscription;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SubscriptionEntity>
 *
 * @method void add(SubscriptionEntity $entity)
 * @method void set(string $key, SubscriptionEntity $entity)
 * @method SubscriptionEntity[] getIterator()
 * @method SubscriptionEntity[] getElements()
 * @method null|SubscriptionEntity get(string $key)
 * @method null|SubscriptionEntity first()
 * @method null|SubscriptionEntity last()
 */
class SubscriptionCollection extends EntityCollection
{
    /**
     * @return array<SubscriptionEntity>
     */
    public function getFlatList(): array
    {
        return $this->buildFlat($this);
    }

    public function filterByStatus(string $status): self
    {
        $filtered = new self();
        foreach ($this as $subscription) {
            if ($subscription->getStatus() === $status) {
                $filtered->add($subscription);
            }
        }

        return $filtered;
    }

    protected function getExpectedClass(): string
    {
        return SubscriptionEntity::class;
    }

    /**
     * @return array<SubscriptionEntity>
     */
    private function buildFlat(SubscriptionCollection $lineItems): array
    {
        $flat = [];
        foreach ($lineItems as $lineItem) {
            $flat[] = $lineItem;
        }

        return $flat;
    }
}
