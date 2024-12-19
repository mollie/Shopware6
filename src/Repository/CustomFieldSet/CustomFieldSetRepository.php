<?php

namespace Kiener\MolliePayments\Repository\CustomFieldSet;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class CustomFieldSetRepository implements CustomFieldSetRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $coreRepository;

    /**
     * @param EntityRepository $customFieldSetRepository
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
