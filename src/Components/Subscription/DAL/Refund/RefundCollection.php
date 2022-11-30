<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Refund;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                add(RefundEntity $entity)
 * @method void                set(string $key, RefundEntity $entity)
 * @method RefundEntity[]    getIterator()
 * @method RefundEntity[]    getElements()
 * @method RefundEntity|null get(string $key)
 * @method RefundEntity|null first()
 * @method RefundEntity|null last()
 */
class RefundCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RefundEntity::class;
    }
}
