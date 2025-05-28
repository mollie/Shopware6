<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class UpdateOrderCustomFields
{
    /**
     * @var EntityRepository<EntityCollection<OrderEntity>>
     */
    private $orderRepository;

    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct($orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function updateOrder(string $shopwareOrderId, OrderAttributes $struct, Context $context): void
    {
        $data = [
            'id' => $shopwareOrderId,
            'customFields' => $struct->toArray(),
        ];

        $this->orderRepository->update([$data], $context);
    }
}
