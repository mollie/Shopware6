<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\CustomerAddress;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

interface CustomerAddressRepositoryInterface
{
    /**
     * @param array<mixed> $ids
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent;
}
