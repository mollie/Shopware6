<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SubscriptionAddressEntity>
 * @method void add(SubscriptionEntity $entity)
 * @method void set(string $key, SubscriptionEntity $entity)
 * @method SubscriptionAddressEntity[]     getIterator()
 * @method SubscriptionAddressEntity[]     getElements()
 * @method null|SubscriptionAddressEntity  get(string $key)
 * @method null|SubscriptionAddressEntity  first()
 * @method null|SubscriptionAddressEntity  last()
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
