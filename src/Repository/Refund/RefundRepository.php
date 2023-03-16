<?php

namespace Kiener\MolliePayments\Repository\Refund;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class RefundRepository implements RefundRepositoryInterface
{

    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $coreRepository;


    /**
     * @param EntityRepository|EntityRepositoryInterface $refundRepository
     */
    public function __construct($refundRepository)
    {
        $this->coreRepository = $refundRepository;
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

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function create(array $data, Context $context): void
    {
        $this->coreRepository->create($data, $context);
    }
}
