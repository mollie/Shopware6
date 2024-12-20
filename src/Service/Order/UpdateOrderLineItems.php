<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderLineItems
{
    /**
     * @var EntityRepository
     */
    private $orderLineRepository;


    /**
     * @param EntityRepository $orderLineRepository
     */
    public function __construct(EntityRepository $orderLineRepository)
    {
        $this->orderLineRepository = $orderLineRepository;
    }

    /**
     * @param OrderLine[] $orderLines
     * @param SalesChannelContext $salesChannelContext
     * @return void
     */
    public function updateOrderLineItems(array $orderLines, OrderLineItemCollection $shopwareOrderLines, SalesChannelContext $salesChannelContext): void
    {
        $updateLines = [];
        foreach ($orderLines as $orderLine) {
            if ($orderLine->type === OrderLineType::TYPE_SHIPPING_FEE) {
                continue;
            }

            $shopwareLineItemId = (string)$orderLine->metadata->orderLineItemId;

            if (empty($shopwareLineItemId)) {
                continue;
            }
            /** @var OrderLineItemEntity $shopwareLine */
            $shopwareLine = $shopwareOrderLines->get($shopwareLineItemId);
            if (! $shopwareLine instanceof OrderLineItemEntity) {
                continue;
            }

            ## we need some customfields for later when we edit an order, for example subscription information
            $originalCustomFields = $shopwareLine->getPayload()['customFields'] ?? [];
            $originalCustomFields['order_line_id'] = $orderLine->id;

            $updateLines[] = [
                'id' => $shopwareLine->getId(),
                'customFields' => [
                    CustomFieldsInterface::MOLLIE_KEY => $originalCustomFields
                ],
            ];
        }
        if (count($updateLines) === 0) {
            return;
        }
        $this->orderLineRepository->update($updateLines, $salesChannelContext->getContext());
    }
}
