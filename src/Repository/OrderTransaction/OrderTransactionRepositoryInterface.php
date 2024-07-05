<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\OrderTransaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

interface OrderTransactionRepositoryInterface
{
    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent;

    /**
     * @param string $orderID
     * @param Context $context
     * @return OrderTransactionEntity
     */
    public function getLatestOrderTransaction(string $orderID, Context $context): OrderTransactionEntity;
}
