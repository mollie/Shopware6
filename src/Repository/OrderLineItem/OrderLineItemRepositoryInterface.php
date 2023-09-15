<?php

namespace Kiener\MolliePayments\Repository\OrderLineItem;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

interface OrderLineItemRepositoryInterface
{
    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent;
}
