<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderLineItems
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderLineRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderDeliveryRepository;


    /**
     * @param EntityRepositoryInterface $orderLineRepository
     */
    public function __construct(EntityRepositoryInterface $orderLineRepository, EntityRepositoryInterface $orderDeliveryRepository)
    {
        $this->orderLineRepository = $orderLineRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /**
     * @param Order $mollieOrder
     * @param SalesChannelContext $salesChannelContext
     */
    public function updateOrderLineItems(Order $mollieOrder, SalesChannelContext $salesChannelContext): void
    {
        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            ##Contrary to the name, this can also be an order_delivery id since these are also kind of line items
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

            if ($orderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                $this->orderDeliveryRepository->update([$data], $salesChannelContext->getContext());
            } else {
                $this->orderLineRepository->update([$data], $salesChannelContext->getContext());
            }
        }
    }
}
