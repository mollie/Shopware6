<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
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
    ): RefundEntity {
        $entityId = Uuid::randomHex();

        $refundData = [
            'id' => $entityId,
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
        } elseif (count($stockItems) > 0) {
            $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
            $refundData['refundItems'] = $this->buildRefundItemsFromRequest($stockItems, $orderLineItems);
        }

        if (count($stockItems) > 0) {
            $this->applyStockAlterations($stockItems, $order, $context);
        }

        $this->refundRepository->upsert([$refundData], $context);

        $criteria = new Criteria([$entityId]);
        $criteria->addAssociation('refundItems');

        $entity = $this->refundRepository->search($criteria, $context)->first();

        if (! $entity instanceof RefundEntity) {
            throw new \RuntimeException(sprintf('Refund entity "%s" could not be loaded after upsert.', $entityId));
        }

        return $entity;
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
     *
     * @return array<array<string, mixed>>
     */
    private function buildRefundItemsFromRequest(array $requestItems, OrderLineItemCollection $orderLineItems): array
    {
        $result = [];

        foreach ($requestItems as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $totalAmount = (float) ($item['amount'] ?? 0.0);
            $lineItemId = (string) ($item['id'] ?? '');

            // Only set orderLineItemId if the ID actually exists in order_line_item;
            // delivery IDs are not in that table and would violate the FK constraint.
            $orderLineItem = $orderLineItems->get($lineItemId);

            $result[] = [
                'mollieLineId' => '',
                'label' => (string) ($item['label'] ?? ''),
                'quantity' => $qty,
                'amount' => $totalAmount / $qty,
                'orderLineItemId' => $orderLineItem !== null ? $lineItemId : null,
                'orderLineItemVersionId' => $orderLineItem?->getVersionId(),
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
