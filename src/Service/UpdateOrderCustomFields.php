<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Shopware\Core\Framework\Context;

class UpdateOrderCustomFields
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(OrderRepositoryInterface $orderRepository)
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
