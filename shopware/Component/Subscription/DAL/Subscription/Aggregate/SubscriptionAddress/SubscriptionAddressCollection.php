<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SubscriptionAddressEntity>
 *
 * @method void add(SubscriptionEntity $entity)
 * @method void set(string $key, SubscriptionEntity $entity)
 * @method SubscriptionAddressEntity[] getIterator()
 * @method SubscriptionAddressEntity[] getElements()
 * @method null|SubscriptionAddressEntity get(string $key)
 * @method null|SubscriptionAddressEntity first()
 * @method null|SubscriptionAddressEntity last()
 */
class SubscriptionAddressCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SubscriptionAddressEntity::class;
    }
}
