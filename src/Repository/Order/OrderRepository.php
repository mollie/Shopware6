<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Order;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

class OrderRepository
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrders;


    /**
     * @param EntityRepositoryInterface $repoOrders
     */
    public function __construct(EntityRepositoryInterface $repoOrders)
    {
        $this->repoOrders = $repoOrders;
    }


    /**
     * Searches orders of the provided customer with the provided Mollie ID (ord_xyz or tr_xyz) in Shopware.
     * @param string $customerId
     * @param string $mollieId
     * @param Context $context
     * @return \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult
     */
    public function findByMollieId(string $customerId, string $mollieId, Context $context)
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
