<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\SubscriptionToProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                   add(SubscriptionToProductEntity $entity)
 * @method void                                   set(string $key, SubscriptionToProductEntity $entity)
 * @method SubscriptionToProductEntity[]     getIterator()
 * @method SubscriptionToProductEntity[]     getElements()
 * @method SubscriptionToProductEntity|null  get(string $key)
 * @method SubscriptionToProductEntity|null  first()
 * @method SubscriptionToProductEntity|null  last()
 */
class SubscriptionToProductCollection extends EntityCollection
{
    /**
     * @return string
     */
    protected function getExpectedClass(): string
    {
        return SubscriptionToProductEntity::class;
    }
}
