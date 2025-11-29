<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final class FakeOrderTransactionRepository implements OrderTransactionRepositoryInterface
{
    public function findOpenTransactions(?Context $context = null): IdSearchResult
    {
        // TODO: Implement findOpenTransactions() method.
    }

    public function savePaymentExtension(OrderTransactionEntity $orderTransactionEntity, Payment $payment, Context $context): EntityWrittenContainerEvent
    {
        // TODO: Implement savePaymentExtension() method.
    }

    public function findById(string $orderTransactionId, Context $context): ?OrderTransactionEntity
    {
        // TODO: Implement findById() method.
    }
}
