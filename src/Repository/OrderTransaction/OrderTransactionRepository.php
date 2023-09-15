<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\OrderTransaction;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

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
}
