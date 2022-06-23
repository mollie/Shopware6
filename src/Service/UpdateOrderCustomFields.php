<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderCustomFields
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {

        $this->orderRepository = $orderRepository;
    }

    public function updateOrder(string $shopwareOrderId, OrderAttributes $struct, SalesChannelContext $salesChannelContext): void
    {
        $data = [
            'id' => $shopwareOrderId,
            'customFields' => $struct->toArray()
        ];

        $this->orderRepository->update([$data], $salesChannelContext->getContext());
    }
}
