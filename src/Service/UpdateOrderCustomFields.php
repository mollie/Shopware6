<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class UpdateOrderCustomFields
{
    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @param EntityRepository $orderRepository
     */
    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param string $shopwareOrderId
     * @param OrderAttributes $struct
     * @param Context $context
     * @return void
     */
    public function updateOrder(string $shopwareOrderId, OrderAttributes $struct, Context $context): void
    {
        $data = [
            'id' => $shopwareOrderId,
            'customFields' => $struct->toArray()
        ];

        $this->orderRepository->update([$data], $context);
    }
}
