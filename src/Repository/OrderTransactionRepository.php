<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class OrderTransactionRepository
{
    private EntityRepository $repository;

    public function __construct(EntityRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getRepository(): EntityRepository
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

        return $this->repository->search($criteria, $context)->first();
    }
}
