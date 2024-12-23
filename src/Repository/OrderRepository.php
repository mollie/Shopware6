<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

class OrderRepository
{
    private EntityRepository $repository;
    public function __construct(EntityRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getRepository(): EntityRepository
    {
        return $this->repository;
    }
    /**
     * Searches orders of the provided customer with the provided Mollie ID (ord_xyz or tr_xyz) in Shopware.
     * @param string $customerId
     * @param string $mollieId
     * @param Context $context
     * @return EntitySearchResult
     */
    public function findByMollieId(string $customerId, string $mollieId, Context $context) : EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('customFields.mollie_payments.order_id', $mollieId),
            new EqualsFilter('customFields.mollie_payments.payment_id', $mollieId)
        ]));

        return $this->repository->search($criteria, $context);
    }
}