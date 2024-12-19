<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\OrderTransaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class OrderTransactionRepository implements OrderTransactionRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $orderTransactionRepository;

    /**
     * @param EntityRepository $orderTransactionRepository
     */
    public function __construct($orderTransactionRepository)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->orderTransactionRepository->search($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderTransactionRepository->update($data, $context);
    }

    /**
     * @param string $orderID
     * @param Context $context
     * @return OrderTransactionEntity
     */
    public function getLatestOrderTransaction(string $orderID, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderID));
        $criteria->addAssociation('order');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('paymentMethod');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        /** @var EntitySearchResult<OrderTransactionEntity> $result */
        $result = $this->orderTransactionRepository->search($criteria, $context);

        return $result->first();
    }
}
