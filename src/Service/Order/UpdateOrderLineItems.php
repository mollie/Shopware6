<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderLineItems
{
    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $orderLineRepository;

    public function __construct(EntityRepositoryInterface $orderLineRepository)
    {

        $this->orderLineRepository = $orderLineRepository;
    }

    public function updateOrderLineItems(MollieOrder $mollieOrder, SalesChannelContext $context): void
    {
        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            $shopwareLineItemId = (string)$orderLine->metadata->orderLineItemId ?? '';

            if (empty($shopwareLineItemId)) {
                continue;
            }

            $data = [
                'id' => $shopwareLineItemId,
                'customFields' => [
                    'mollie_payments' => [
                        'order_line_id' => $orderLine->id
                    ]
                ]
            ];

            $this->orderLineRepository->update($data, $salesChannelContext->getContext());
        }
    }
}
