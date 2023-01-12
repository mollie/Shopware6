<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SubscriptionEntity>
 * @method void add(SubscriptionEntity $entity)
 * @method void set(string $key, SubscriptionEntity $entity)
 * @method SubscriptionEntity[]     getIterator()
 * @method SubscriptionEntity[]     getElements()
 * @method null|SubscriptionEntity  get(string $key)
 * @method null|SubscriptionEntity  first()
 * @method null|SubscriptionEntity  last()
 */
class SubscriptionCollection extends EntityCollection
{
    /**
     * @return string
     */
    protected function getExpectedClass(): string
    {
        return SubscriptionEntity::class;
    }

    /**
     * @return array<SubscriptionEntity>
     */
    public function getFlatList(): array
    {
        return $this->buildFlat($this);
    }

    /**
     * @param SubscriptionCollection $lineItems
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
