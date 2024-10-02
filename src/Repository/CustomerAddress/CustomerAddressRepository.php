<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\CustomerAddress;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

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
