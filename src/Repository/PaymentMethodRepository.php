<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/**
 * @TODO: remove after dropping 6.4 this is only required because in 6.4 a decoredated class is provided
 */
class PaymentMethodRepository
{
    /**
     * @var EntityRepository<EntityCollection<PaymentMethodEntity>>|EntityRepositoryInterface<EntityCollection<PaymentMethodEntity>>
     */
    private $repository;

    /**
     * @param EntityRepository<EntityCollection<PaymentMethodEntity>>|EntityRepositoryInterface<EntityCollection<PaymentMethodEntity>> $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<PaymentMethodEntity>>|EntityRepositoryInterface<EntityCollection<PaymentMethodEntity>>
     */
    public function getRepository()
    {
        return $this->repository;
    }
}
