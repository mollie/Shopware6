<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

final class RefundItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RefundItemEntity::class;
    }
}
