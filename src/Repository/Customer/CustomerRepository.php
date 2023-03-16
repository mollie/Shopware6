<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class CustomerRepository implements CustomerRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $customerRepository
     */
    public function __construct($customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function upsert(array $data, Context $context): void
    {
        $this->customerRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function create(array $data, Context $context): void
    {
        $this->customerRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->customerRepository->search($criteria, $context);
    }
}
