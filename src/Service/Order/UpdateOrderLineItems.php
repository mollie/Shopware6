<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Kiener\MolliePayments\Repository\OrderLineItem\OrderLineItemRepositoryInterface;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderLineItems
{
    /**
     * @var OrderLineItemRepositoryInterface
     */
    private $orderLineRepository;


    /**
     * @param OrderLineItemRepositoryInterface $orderLineRepository
     */
    public function __construct(OrderLineItemRepositoryInterface $orderLineRepository)
    {
        $this->orderLineRepository = $orderLineRepository;
    }

    /**
     * @param OrderLine[] $orderLines
     * @param SalesChannelContext $salesChannelContext
     * @return void
     */
    public function updateOrderLineItems(array $orderLines, SalesChannelContext $salesChannelContext): void
    {
        foreach ($orderLines as $orderLine) {
            if ($orderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                continue;
            }

            $shopwareLineItemId = (string)$orderLine->metadata->orderLineItemId;

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
