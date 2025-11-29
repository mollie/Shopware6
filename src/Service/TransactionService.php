<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Mollie\Shopware\Component\Transaction\TransactionNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class TransactionService
{
    /**
     * @var EntityRepository<EntityCollection<OrderTransactionEntity>>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * Creates a new instance of the transaction service.
     *
     * @param EntityRepository<EntityCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(EntityRepository $orderTransactionRepository)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    public function getTransactionById(string $transactionId,?string $versionId = null, ?Context $context = null): OrderTransactionEntity
    {
        $transactionCriteria = new Criteria();
        $transactionCriteria->addFilter(new EqualsFilter('id', $transactionId));

        if ($versionId !== null) {
            $transactionCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        }
        $transactionCriteria->addAssociation('paymentMethod');
        $transactionCriteria->addAssociation('order.currency');
        $transactionCriteria->addAssociation('order.lineItems');
        $transactionCriteria->addAssociation('order.stateMachineState');

        /** @var EntitySearchResult<OrderTransactionCollection<OrderTransactionEntity>> $transactions */
        $transactions = $this->orderTransactionRepository->search(
            $transactionCriteria,
            $context ?? new Context(new SystemSource()),
        );

        if ($transactions->count() === 0) {
            throw new TransactionNotFoundException($transactionId);
        }

        $transaction = $transactions->first();
        if ($transaction === null) {
            throw new TransactionNotFoundException($transactionId);
        }

        return $transaction;
    }

    /**
     * Updates a transaction in the database.
     */
    public function updateTransaction(OrderTransactionEntity $transaction, ?Context $context = null): EntityWrittenContainerEvent
    {
        return $this->orderTransactionRepository->update(
            [$transaction->getVars()],
            $context ?? Context::createDefaultContext()
        );
    }
}
