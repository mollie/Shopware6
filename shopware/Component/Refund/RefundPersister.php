<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RefundPersister
{
    /**
     * @param EntityRepository<RefundCollection> $refundRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_refund.repository')]
        private readonly EntityRepository $refundRepository,
        #[Autowire(service: StockStorage::class)]
        private readonly AbstractStockStorage $stockStorage,
    ) {
    }

    /**
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $stockItems
     */
    public function persist(
        OrderEntity $order,
        MollieRefund $refund,
        CreateRefund $createRefund,
        string $refundType,
        string $description,
        string $internalDescription,
        array $stockItems,
        Context $context
    ): void {
        $refundData = [
            'orderId' => $order->getId(),
            'orderVersionId' => $order->getVersionId(),
            'mollieRefundId' => $refund->getId(),
            'type' => $refundType,
            'publicDescription' => $description,
            'internalDescription' => $internalDescription,
        ];

        $lines = $createRefund->getLines();
        if ($lines->count() > 0) {
            $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
            $orderDeliveries = $order->getDeliveries() ?? new OrderDeliveryCollection();
            $refundData['refundItems'] = $this->buildRefundItems($lines, $orderLineItems, $orderDeliveries);

            if (count($stockItems) > 0) {
                $this->applyStockAlterations($stockItems, $order, $context);
            }
        }

        $this->refundRepository->upsert([$refundData], $context);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildRefundItems(LineItemCollection $lineItems, OrderLineItemCollection $orderLineItems, OrderDeliveryCollection $orderDeliveries): array
    {
        $result = [];

        foreach ($lineItems as $item) {
            $shopwareId = $item->getShopwareLineItemId();

            if ($item->getType() === LineItemType::SHIPPING) {
                $orderLineItemId = null;
                $orderLineItemVersionId = $orderDeliveries->get($shopwareId)?->getVersionId();
            } else {
                $orderLineItemId = $shopwareId ?: null;
                $orderLineItemVersionId = $orderLineItems->get($shopwareId)?->getVersionId();
            }

            $result[] = [
                'mollieLineId' => $item->getId(),
                'label' => $item->getDescription(),
                'quantity' => $item->getQuantity(),
                'amount' => (float) $item->getUnitPrice()->getValue(),
                'orderLineItemId' => $orderLineItemId,
                'orderLineItemVersionId' => $orderLineItemVersionId,
            ];
        }

        return $result;
    }

    /**
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $requestItems
     */
    private function applyStockAlterations(array $requestItems, OrderEntity $order, Context $context): void
    {
        $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $alterations = [];

        foreach ($requestItems as $item) {
            $lineItemId = (string) ($item['id'] ?? '');
            $orderLineItem = $orderLineItems->get($lineItemId);

            if (! $orderLineItem instanceof OrderLineItemEntity) {
                continue;
            }

            $stockQty = min((int) ($item['resetStock'] ?? 0), $orderLineItem->getQuantity());
            $productId = $orderLineItem->getReferencedId();

            if ($stockQty > 0 && $productId !== null) {
                $alterations[] = new StockAlteration($orderLineItem->getId(), $productId, $stockQty, 0);
            }
        }

        if (count($alterations) > 0) {
            $this->stockStorage->alter($alterations, $context);
        }
    }
}
