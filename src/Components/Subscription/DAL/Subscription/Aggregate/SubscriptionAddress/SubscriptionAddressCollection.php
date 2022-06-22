<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(SubscriptionEntity $entity)
 * @method void set(string $key, SubscriptionEntity $entity)
 * @method SubscriptionAddressEntity[]     getIterator()
 * @method SubscriptionAddressEntity[]     getElements()
 * @method SubscriptionAddressEntity|null  get(string $key)
 * @method SubscriptionAddressEntity|null  first()
 * @method SubscriptionAddressEntity|null  last()
 */
class SubscriptionAddressCollection extends EntityCollection
{
    /**
     * @return string
     */
    protected function getExpectedClass(): string
    {
        return SubscriptionAddressEntity::class;
    }
}
