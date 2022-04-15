<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(SubscriptionEntity $entity)
 * @method void set(string $key, SubscriptionEntity $entity)
 * @method SubscriptionEntity[]     getIterator()
 * @method SubscriptionEntity[]     getElements()
 * @method SubscriptionEntity|null  get(string $key)
 * @method SubscriptionEntity|null  first()
 * @method SubscriptionEntity|null  last()
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
}
