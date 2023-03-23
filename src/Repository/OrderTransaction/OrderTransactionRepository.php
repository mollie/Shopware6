<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\OrderTransactions;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderTransactionRepository implements OrderTransactionRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $orderTransactionRepository
     */
    public function __construct($orderTransactionRepository)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function upsert(array $data, Context $context): void
    {
        $this->orderTransactionRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function create(array $data, Context $context): void
    {
        $this->orderTransactionRepository->create($data, $context);
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
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->orderTransactionRepository->searchIds($criteria,$context);
    }

    /**
     * @param array $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderTransactionRepository->update($data,$context);
    }
}
