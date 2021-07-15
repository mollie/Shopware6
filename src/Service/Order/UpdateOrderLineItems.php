<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderLineItems
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderLineRepository;

    public function __construct(EntityRepositoryInterface $orderLineRepository)
    {

        $this->orderLineRepository = $orderLineRepository;
    }

    public function updateOrderLineItems(Order $mollieOrder, SalesChannelContext $salesChannelContext): void
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

            $this->orderLineRepository->update([$data], $salesChannelContext->getContext());
        }
    }
}
