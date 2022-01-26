<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Order;


use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

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
