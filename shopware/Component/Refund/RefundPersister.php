<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Mollie;
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
    private const AMOUNT_TOLERANCE = 0.005;

    /**
     * @param EntityRepository<RefundCollection> $refundRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_refund.repository')]
        private readonly EntityRepository $refundRepository,
        #[Autowire(service: StockStorage::class)]
        private readonly AbstractStockStorage $stockStorage,
        private readonly RefundItemSplitter $refundItemSplitter,
    ) {
    }

    /**
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $stockItems
     * @param array<string, float> $refundedPerLine already-refunded amount per line item / delivery id
     * @param array<string, array{max: float, quantity: int}> $lineInfo max amount + quantity per line item / delivery id
     */
    public function persist(
        OrderEntity $order,
        MollieRefund $refund,
        CreateRefund $createRefund,
        string $refundType,
        string $description,
        string $internalDescription,
        array $stockItems,
        array $refundedPerLine,
        array $lineInfo,
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
            $orderDeliveries = $order->getDeliveries() ?? new OrderDeliveryCollection();
            $refundData['refundItems'] = $this->buildRefundItemsFromRequest($stockItems, $orderLineItems, $orderDeliveries, $refundedPerLine, $lineInfo, $refund->getAmount()->getValue());
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

            $orderDeliveryId = null;
            if ($item->getType() === LineItemType::SHIPPING) {
                $orderLineItemId = null;
                $orderLineItemVersionId = $orderDeliveries->get($shopwareId)?->getVersionId();
                $orderDeliveryId = $orderDeliveries->has($shopwareId) ? $shopwareId : null;
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
                'orderDeliveryId' => $orderDeliveryId,
            ];
        }

        return $result;
    }

    /**
     * Builds the refund item rows from the requested items. The amount of each line is split
     * into full units (quantity > 0, amount per unit) plus a partial remainder (quantity 0,
     * amount is the total) so the composition reflects the real per-unit breakdown. Any amount
     * that exceeds the line item maximum is booked as a separate line-less "misc" entry. The
     * total distributed never exceeds the actual Mollie refund amount.
     *
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $requestItems
     * @param array<string, float> $refundedPerLine
     * @param array<string, array{max: float, quantity: int}> $lineInfo
     *
     * @return array<array<string, mixed>>
     */
    private function buildRefundItemsFromRequest(array $requestItems, OrderLineItemCollection $orderLineItems, OrderDeliveryCollection $orderDeliveries, array $refundedPerLine, array $lineInfo, float $refundTotal): array
    {
        $result = [];
        $budget = round($refundTotal, Mollie::ROUNDING_PRECISION);
        $distributed = 0.0;

        foreach ($requestItems as $item) {
            $lineItemId = (string) ($item['id'] ?? '');
            $requestedAmount = round((float) ($item['amount'] ?? 0.0), Mollie::ROUNDING_PRECISION);

            if ($requestedAmount <= 0.0) {
                continue;
            }

            // Only set orderLineItemId if the ID actually exists in order_line_item;
            // delivery IDs are not in that table and would violate the FK constraint.
            $orderLineItem = $orderLineItems->get($lineItemId);
            $isDelivery = $orderLineItem === null && $orderDeliveries->has($lineItemId);
            $orderLineItemId = $orderLineItem !== null ? $lineItemId : null;
            $orderLineItemVersionId = $orderLineItem?->getVersionId();
            $orderDeliveryId = $isDelivery ? $lineItemId : null;
            $label = (string) ($item['label'] ?? '');

            $lineMax = $lineInfo[$lineItemId]['max'] ?? $requestedAmount;
            $quantity = $lineInfo[$lineItemId]['quantity'] ?? 1;
            $alreadyRefunded = $refundedPerLine[$lineItemId] ?? 0.0;

            $split = $this->refundItemSplitter->split($requestedAmount, $lineMax, $quantity, $alreadyRefunded);

            $fullUnits = max(1, $split['fullUnits']);
            if ($split['fullUnits'] > 0) {
                $amount = $this->takeFromBudget($split['fullUnits'] * $split['unitPrice'], $distributed, $budget);
                if ($amount > 0.0) {
                    $result[] = $this->refundItemRow($label, $fullUnits, $amount / $fullUnits, $orderLineItemId, $orderLineItemVersionId, $orderDeliveryId);
                }
            }

            if ($split['remainder'] > self::AMOUNT_TOLERANCE) {
                $amount = $this->takeFromBudget($split['remainder'], $distributed, $budget);
                if ($amount > 0.0) {
                    $result[] = $this->refundItemRow($label, 0, $amount, $orderLineItemId, $orderLineItemVersionId, $orderDeliveryId);
                }
            }

            if ($split['excess'] > self::AMOUNT_TOLERANCE) {
                $amount = $this->takeFromBudget($split['excess'], $distributed, $budget);
                if ($amount > 0.0) {
                    $result[] = $this->refundItemRow('', 0, $amount, null, null, null);
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function refundItemRow(string $label, int $quantity, float $amount, ?string $orderLineItemId, ?string $orderLineItemVersionId, ?string $orderDeliveryId): array
    {
        return [
            'mollieLineId' => '',
            'label' => $label,
            'quantity' => $quantity,
            'amount' => round($amount, Mollie::ROUNDING_PRECISION),
            'orderLineItemId' => $orderLineItemId,
            'orderLineItemVersionId' => $orderLineItemVersionId,
            'orderDeliveryId' => $orderDeliveryId,
        ];
    }

    private function takeFromBudget(float $amount, float &$distributed, float $budget): float
    {
        $remaining = round($budget - $distributed, Mollie::ROUNDING_PRECISION);

        if ($remaining <= 0.0) {
            return 0.0;
        }

        $take = min($amount, $remaining);
        $distributed = round($distributed + $take, Mollie::ROUNDING_PRECISION);

        return $take;
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
