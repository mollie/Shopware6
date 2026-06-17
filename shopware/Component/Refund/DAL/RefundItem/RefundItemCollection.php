<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\DAL\RefundItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<RefundItemEntity>
 *
 * @method void add(RefundItemEntity $entity)
 * @method void set(string $key, RefundItemEntity $entity)
 * @method RefundItemEntity[] getIterator()
 * @method RefundItemEntity[] getElements()
 * @method null|RefundItemEntity get(string $key)
 * @method null|RefundItemEntity first()
 * @method null|RefundItemEntity last()
 */
final class RefundItemCollection extends EntityCollection
{
    /**
     * @return RefundItemEntity[]
     */
    public function jsonSerialize(): array
    {
        return array_values($this->getElements());
    }

    protected function getExpectedClass(): string
    {
        return RefundItemEntity::class;
    }
}
