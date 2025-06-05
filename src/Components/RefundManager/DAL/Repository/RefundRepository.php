<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Repository;

use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class RefundRepository implements RefundRepositoryInterface
{
    /**
     * @var EntityRepository<RefundCollection<RefundEntity>>
     */
    private $mollieRefundRepository;

    /**
     * @param EntityRepository<RefundCollection<RefundEntity>> $mollieRefundRepository
     */
    public function __construct($mollieRefundRepository)
    {
        $this->mollieRefundRepository = $mollieRefundRepository;
    }

    /**
     * @param array<mixed> $data
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->mollieRefundRepository->create($data, $context);
    }

    /**
     * @return EntitySearchResult<RefundCollection<RefundEntity>>
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->mollieRefundRepository->search($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->mollieRefundRepository->searchIds($criteria, $context);
    }

    /**
     * @param array<mixed> $ids
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->mollieRefundRepository->delete($ids, $context);
    }
}
