<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Refund;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<RefundEntity>
 * @method void add(RefundEntity $entity)
 * @method void set(string $key, RefundEntity $entity)
 * @method RefundEntity[]     getIterator()
 * @method RefundEntity[]     getElements()
 * @method null|RefundEntity  get(string $key)
 * @method null|RefundEntity  first()
 * @method null|RefundEntity  last()
 */
class RefundCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RefundEntity::class;
    }
}
