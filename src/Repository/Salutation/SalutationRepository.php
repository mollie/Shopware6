<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Salutation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class SalutationRepository implements SalutationRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $salutationRepository;

    /**
     * @param EntityRepository $salutationRepository
     */
    public function __construct($salutationRepository)
    {
        $this->salutationRepository = $salutationRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function upsert(array $data, Context $context): void
    {
        $this->salutationRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function create(array $data, Context $context): void
    {
        $this->salutationRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->salutationRepository->search($criteria, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->salutationRepository->searchIds($criteria, $context);
    }
}
