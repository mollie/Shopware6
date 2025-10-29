<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Mollie\Shopware\Entity\OrderTransaction\OrderTransaction;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

interface OrderTransactionRepositoryInterface
{
    public function findOpenTransactions(?Context $context = null): IdSearchResult;

    public function saveTransactionData(OrderTransactionEntity $shopwareOrderTransaction, OrderTransaction $mollieTransactionData, Context $context): EntityWrittenContainerEvent;

    public function findById(string $orderTransactionId, Context $context): ?OrderTransactionEntity;
}
