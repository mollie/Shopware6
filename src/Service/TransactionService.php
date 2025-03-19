<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class TransactionService
{
    /**
     * @var EntityRepository
     */
    private $orderTransactionRepository;

    /**
     * Creates a new instance of the transaction service.
     *
     * @param mixed $orderTransactionRepository
     */
    public function __construct($orderTransactionRepository)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @param string $transactionId
     * @param null|string $versionId
     */
    public function getTransactionById($transactionId, $versionId = null, ?Context $context = null): ?OrderTransactionEntity
    {
        $transactionCriteria = new Criteria();
        $transactionCriteria->addFilter(new EqualsFilter('id', $transactionId));

        if ($versionId !== null) {
            $transactionCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        }

        $transactionCriteria->addAssociation('order.currency');
        $transactionCriteria->addAssociation('order.lineItems');
        $transactionCriteria->addAssociation('order.stateMachineState');

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->orderTransactionRepository->search(
            $transactionCriteria,
            $context ?? Context::createDefaultContext()
        );

        if ($transactions->count() === 0) {
            return null;
        }

        return $transactions->first();
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
