<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\CustomerAddress;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class CustomerAddressRepository implements CustomerAddressRepositoryInterface
{
    /**
     * @var EntityRepository<CustomerAddressCollection>
     */
    private $customerAddressRepository;

    /**
     * @param EntityRepository<CustomerAddressCollection> $customerAddressRepository
     */
    public function __construct($customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }


    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->create($data, $context);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->customerAddressRepository->search($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->update($data, $context);
    }

    /**
     * @param array<mixed> $ids
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->customerAddressRepository->delete($ids, $context);
    }
}
