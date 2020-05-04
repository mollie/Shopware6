<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class TransactionService
{
    /** @var EntityRepositoryInterface $orderTransactionRepository */
    private $orderTransactionRepository;

    /**
     * Creates a new instance of the transaction service.
     *
     * @param EntityRepositoryInterface $orderTransactionRepository
     */
    public function __construct(
        EntityRepositoryInterface $orderTransactionRepository
    )
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * Returns the order transaction repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->orderTransactionRepository;
    }

    /**
     * Finds a transaction by id.
     *
     * @param $transactionId
     * @param $versionId
     * @param Context|null $context
     * @return OrderTransactionEntity|null
     * @throws InconsistentCriteriaIdsException
     */
    public function getTransactionById(
        $transactionId,
        $versionId = null,
        Context $context = null
    ): ?OrderTransactionEntity
    {
        $transactionCriteria = new Criteria();
        $transactionCriteria->addFilter(new EqualsFilter('id', $transactionId));

        if ($versionId !== null) {
            $transactionCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        }

        $transactionCriteria->addAssociation('order');

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->getRepository()->search(
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
     *
     * @param OrderTransactionEntity $transaction
     * @param Context|null $context
     * @return EntityWrittenContainerEvent
     */
    public function updateTransaction(
        OrderTransactionEntity $transaction,
        Context $context = null
    ): EntityWrittenContainerEvent
    {
        return $this->getRepository()->update(
            [$transaction->getVars()],
            $context ?? Context::createDefaultContext()
        );
    }
}