<?php

namespace Kiener\MolliePayments\Repository\CustomFieldSet;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;

class CustomFieldSetRepository implements CustomFieldSetRepositoryInterface
{
    /**
     * @var EntityRepository<CustomFieldSetCollection>
     */
    private $coreRepository;

    /**
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     */
    public function __construct($customFieldSetRepository)
    {
        $this->coreRepository = $customFieldSetRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function upsert(array $data, Context $context): void
    {
        $this->coreRepository->upsert($data, $context);
    }
}
