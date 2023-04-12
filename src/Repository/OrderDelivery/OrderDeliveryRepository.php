<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\OrderDelivery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class OrderDeliveryRepository implements OrderDeliveryRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $orderDeliveryRepository;

    /**
     * @param EntityRepository $orderDeliveryRepository
     */
    public function __construct($orderDeliveryRepository)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->orderDeliveryRepository->search($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderDeliveryRepository->update($data, $context);
    }
}
