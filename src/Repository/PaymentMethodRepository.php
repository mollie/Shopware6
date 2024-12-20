<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/**
 * @TODO: remove after dropping 6.4 this is only required because in 6.4 a decoredated class is provided
 */
class PaymentMethodRepository
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $repository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return EntityRepository|EntityRepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }
}
