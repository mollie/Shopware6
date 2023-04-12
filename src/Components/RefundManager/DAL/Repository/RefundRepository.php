<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class RefundRepository implements RefundRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $mollieRefundRepository;

    /**
     * @param EntityRepository $mollieRefundRepository
     */
    public function __construct($mollieRefundRepository)
    {
        $this->mollieRefundRepository = $mollieRefundRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->mollieRefundRepository->create($data, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->mollieRefundRepository->search($criteria, $context);
    }
}
