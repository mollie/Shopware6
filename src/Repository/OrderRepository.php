<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

class OrderRepository
{
    /**
     * @var EntityRepository<EntityCollection<OrderEntity>>
     */
    private $repository;

    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /** @return EntityRepository<EntityCollection<OrderEntity>> */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Searches orders of the provided customer with the provided Mollie ID (ord_xyz or tr_xyz) in Shopware.
     *
     * @return EntitySearchResult<EntityCollection<OrderEntity>>
     */
    public function findByMollieId(string $customerId, string $mollieId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('customFields.mollie_payments.order_id', $mollieId),
            new EqualsFilter('customFields.mollie_payments.payment_id', $mollieId),
        ]));

        return $this->repository->search($criteria, $context);
    }
}
