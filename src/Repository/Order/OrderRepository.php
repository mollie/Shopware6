<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Order;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderRepository implements OrderRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $repoOrders;


    /**
     * @param EntityRepository $repoOrders
     */
    public function __construct($repoOrders)
    {
        $this->repoOrders = $repoOrders;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->repoOrders->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->repoOrders->create($data, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->repoOrders->search($criteria, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->repoOrders->searchIds($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->repoOrders->update($data, $context);
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

        return $this->repoOrders->search($criteria, $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return void
     */
    public function updateOrderLastUpdated(string $orderId, Context $context): void
    {
        $this->repoOrders->update([
            [
                'id' => $orderId,
                'updatedAt' => new \DateTime(),
            ]
        ], $context);
    }
}
