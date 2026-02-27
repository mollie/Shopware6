<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SubscriptionHistoryEntity>
 *
 * @method void add(SubscriptionHistoryEntity $entity)
 * @method void set(string $key, SubscriptionHistoryEntity $entity)
 * @method SubscriptionHistoryEntity[] getIterator()
 * @method SubscriptionHistoryEntity[] getElements()
 * @method null|SubscriptionHistoryEntity get(string $key)
 * @method null|SubscriptionHistoryEntity first()
 * @method null|SubscriptionHistoryEntity last()
 */
class SubscriptionHistoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SubscriptionHistoryEntity::class;
    }
}
