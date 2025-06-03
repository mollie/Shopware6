<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class OrderTransactionRepository
{
    /**
     * @var EntityRepository<EntityCollection<OrderTransactionEntity>>
     */
    private $repository;

    /**
     * @param EntityRepository<EntityCollection<OrderTransactionEntity>> $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /** @return EntityRepository<EntityCollection<OrderTransactionEntity>> */
    public function getRepository()
    {
        return $this->repository;
    }

    public function getLatestOrderTransaction(string $orderID, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderID));
        $criteria->addAssociation('order');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('paymentMethod');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $searchResult = $this->repository->search($criteria, $context);
        /** @var ?OrderTransactionEntity $orderTransaction */
        $orderTransaction = $searchResult->first();
        if ($orderTransaction === null) {
            throw new \Exception('Order transaction not found for order ID ' . $orderID);
        }

        return $orderTransaction;
    }
}
