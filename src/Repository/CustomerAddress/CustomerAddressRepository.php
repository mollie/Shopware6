<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\CustomerAddress;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class CustomerAddressRepository implements CustomerAddressRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $customerAddressRepository;

    /**
     * @param EntityRepository $customerAddressRepository
     */
    public function __construct($customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }


    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->upsert($data,$context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->create($data,$context);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->customerAddressRepository->search($criteria,$context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->update($data,$context);
    }

}