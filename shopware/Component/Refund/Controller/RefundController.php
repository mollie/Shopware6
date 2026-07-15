<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Controller;

use Mollie\Shopware\Component\FlowBuilder\Event\Refund\RefundStartedEvent;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\LineItem as MollieLineItem;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\RefundCollection as MollieRefundCollection;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\Event\ModifyCreateRefundPayloadEvent;
use Mollie\Shopware\Component\Refund\RefundBuilder;
use Mollie\Shopware\Component\Refund\RefundBuilderInterface;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use Mollie\Shopware\Component\Refund\Struct\RefundOverviewStruct;
use Mollie\Shopware\Component\Refund\Struct\RefundTotalsStruct;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolver;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolverInterface;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
final class RefundController extends AbstractController
{
    private const TYPE_FULL = 'FULL';
    private const TYPE_PARTIAL = 'PARTIAL';
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: RefundBuilder::class)]
        private readonly RefundBuilderInterface $refundBuilder,
        private readonly RefundPersister $refundPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly CreditNoteService $creditNoteService,
        #[Autowire(service: OrderTransactionResolver::class)]
        private readonly OrderTransactionResolverInterface $transactionResolver,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/order/refund-overview',
        name: 'api.action.mollie.order.refund-overview',
        methods: ['POST'],
    )]
    public function overview(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');

        $order = $this->loadOrder($orderId, $context);
        $orderNumber = (string) $order->getOrderNumber();

        $this->logger->info('Refund overview requested', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
        ]);

        $struct = new RefundOverviewStruct();

        $payment = $this->findMolliePayment($order);

        if (! $payment instanceof Payment) {
            $this->logger->debug('No Mollie payment found for refund overview', [
                'orderId' => $orderId,
                'orderNumber' => $orderNumber,
            ]);

            return $this->json($struct);
        }

        $refunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, $order->getSalesChannelId());
        $refunds = $this->enrichRefundsWithComposition($refunds, $order);

        $cart = CartStruct::fromOrder($order);
        $cart->applyRefundedQuantities($this->buildRefundedQuantities($order, $refunds));
        $cart->applyRefundedAmounts($this->buildRefundedAmounts($order, $refunds));

        $struct->setCart($cart);
        $struct->setTotals($this->buildTotals($order, $payment, $refunds));
        $struct->setRefunds($refunds);

        return $this->json($struct);
    }

    #[Route(
        path: '/api/_action/mollie/refund',
        name: 'api.action.mollie.refund',
        methods: ['POST'],
    )]
    public function create(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');

        $order = $this->loadOrder($orderId, $context);
        $payment = $this->extractMolliePayment($order);
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = (string) $order->getSalesChannelId();

        $requestAmount = $request->get('amount');
        $description = (string) $request->get('description', '');
        $internalDescription = (string) $request->get('internalDescription', '');
        $returnId = (string) $request->get('returnId', '');
        /** @var array<array{id: string, quantity: int, amount: float, resetStock: int}> $items */
        $items = $request->get('items', []);
        $items = array_values(array_filter($items, function ($item) {
            return (int) ($item['quantity'] ?? 0) > 0 || (float) ($item['amount'] ?? 0.0) > 0.0;
        }));
        $hasRequestedItems = count($items) > 0;
        $isFullRefund = ($requestAmount === null && ! $hasRequestedItems);
        $refundType = $isFullRefund ? self::TYPE_FULL : self::TYPE_PARTIAL;

        $this->logger->info('Refund create started', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'type' => $refundType,
            'requestAmount' => $requestAmount,
            'hasRequestedItems' => $hasRequestedItems,
            'salesChannelId' => $salesChannelId,
        ]);

        $refundedPerLine = [];
        $lineInfo = [];
        if ($hasRequestedItems) {
            $existingRefunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, $salesChannelId);
            $refundedPerLine = $this->buildRefundedAmounts($order, $existingRefunds);
            $lineInfo = $this->buildLineInfo($order);

            // Cap each requested line to its remaining maximum (line total minus what was
            // already refunded) so a single line can never be over-refunded. Only recompute
            // the refund amount when an explicit amount was requested; a quantity-based line
            // item refund keeps a null amount so the builder derives it from the line items
            // (and the Orders-API line refund path stays intact).
            $items = $this->capItemsToLineMax($items, $refundedPerLine, $lineInfo);
            if ($requestAmount !== null) {
                $requestAmount = array_sum(array_map(function ($item) {
                    return (float) ($item['amount'] ?? 0.0);
                }, $items));
            }
        }

        $createRefund = $this->refundBuilder->build(
            $payment,
            $order,
            $items,
            $description,
            $requestAmount !== null ? (float) $requestAmount : null,
        );

        if ($returnId !== '') {
            $createRefund->setMetadata(['swagReturnId' => $returnId]);
        }

        $refundPayloadEvent = new ModifyCreateRefundPayloadEvent($createRefund, $order, $context);
        /** @var ModifyCreateRefundPayloadEvent $refundPayloadEvent */
        $refundPayloadEvent = $this->eventDispatcher->dispatch($refundPayloadEvent);
        $createRefund = $refundPayloadEvent->getCreateRefund();

        $refund = $this->refundGateway->createRefund($createRefund, $orderNumber, $salesChannelId);

        $stockItems = $hasRequestedItems ? $items : [];
        $dalRefund = $this->refundPersister->persist($order, $refund, $createRefund, $refundType, $description, $internalDescription, $stockItems, $refundedPerLine, $lineInfo, $context);

        $refundSettings = $this->settingsService->getRefundSettings($salesChannelId);
        if ($refundSettings->isCreateCreditNotes()) {
            $this->creditNoteService->addCreditNote($order, $refund, $refundSettings, $context);
        }

        $this->logger->info('Refund created successfully', [
            'orderId' => $order->getId(),
            'orderNumber' => $orderNumber,
            'mollieRefundId' => $refund->getId(),
            'amount' => $createRefund->getAmount()?->getValue(),
            'type' => $refundType,
        ]);

        $refundStartedEvent = new RefundStartedEvent($order, $dalRefund, $refund->getAmount()->getValue(), $context);
        $this->eventDispatcher->dispatch($refundStartedEvent);

        $refund->setRefundItems($dalRefund->getRefundItems());
        $refund->setInternalDescription((string) $dalRefund->getInternalDescription());

        // reload so the refund extension contains the just-persisted refund
        $order = $this->loadOrder($orderId, $context);

        $refunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, $salesChannelId);
        $totals = $this->buildTotals($order, $payment, $refunds);

        return $this->json([
            'refund' => $refund,
            'totals' => $totals,
            'refundedItems' => $this->buildRefundedQuantities($order, $refunds),
            'refundedAmountItems' => $this->buildRefundedAmounts($order, $refunds),
        ]);
    }

    #[Route(
        path: '/api/_action/mollie/refund/cancel',
        name: 'api.action.mollie.refund.cancel',
        methods: ['POST'],
    )]
    public function cancel(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');
        $refundId = (string) $request->get('refundId');

        $order = $this->loadOrder($orderId, $context);
        $payment = $this->extractMolliePayment($order);
        $orderNumber = (string) $order->getOrderNumber();

        $this->logger->info('Refund cancel requested', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'refundId' => $refundId,
        ]);

        $this->refundGateway->cancelRefund($payment->getId(), $refundId, $orderNumber, (string) $order->getSalesChannelId());

        $this->creditNoteService->cancelCreditNote($orderId, $refundId, $context);

        $refunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, (string) $order->getSalesChannelId());
        $totals = $this->buildTotals($order, $payment, $refunds);

        return $this->json([
            'success' => true,
            'totals' => $totals,
            'refundedItems' => $this->buildRefundedQuantities($order, $refunds),
            'refundedAmountItems' => $this->buildRefundedAmounts($order, $refunds),
        ]);
    }

    private function buildTotals(OrderEntity $order, Payment $payment, MollieRefundCollection $refunds): RefundTotalsStruct
    {
        $amountRefunded = $refunds->getSumRefunded();
        $amountPending = $refunds->getSumPending();
        // Use the original refundable total (non-credit line items + shipping), NOT
        // order->getAmountTotal(): credit notes add a negative credit line item and
        // recalculate the order, which would otherwise shrink the total on every refund.
        $refundableTotal = $this->computeRefundableTotal($order);
        $remaining = max(0.0, $refundableTotal - $amountRefunded - $amountPending);

        $this->logger->debug('Refund totals computed', [
            'orderNumber' => $order->getOrderNumber(),
            'amountTotal' => $order->getAmountTotal(),
            'refundableTotal' => $refundableTotal,
            'refunded' => $amountRefunded,
            'pending' => $amountPending,
            'remaining' => $remaining,
            'mollieRefunds' => array_map(function ($refund) {
                return ['amount' => $refund->getAmount()->getValue(), 'status' => $refund->getStatus()->value];
            }, $refunds->jsonSerialize()),
        ]);

        $totals = new RefundTotalsStruct();
        $totals->setRefunded($amountRefunded);
        $totals->setPendingRefunds($amountPending);
        $totals->setRemaining($remaining);
        $totals->setVoucherAmount($payment->getVoucherAmount());
        $totals->setRoundingDiff($payment->getRoundingDiff());

        return $totals;
    }

    /**
     * Computes the original refundable total of the order: the sum of the non-credit line
     * items plus shipping. Credit line items (added by credit notes) and delivery discount
     * placeholders are excluded, so the total stays stable across refunds even though the
     * credit notes recalculate order->getAmountTotal() downwards. Mirrors the base used by
     * RefundBuilder for the refund cap.
     */
    private function computeRefundableTotal(OrderEntity $order): float
    {
        $total = 0.0;

        foreach ($order->getLineItems() ?? new OrderLineItemCollection() as $lineItem) {
            if ($lineItem->getType() === ShopwareLineItem::CREDIT_LINE_ITEM_TYPE) {
                continue;
            }

            if (MollieLineItem::isDeliveryDiscountPlaceholder($lineItem)) {
                continue;
            }

            $total += $lineItem->getTotalPrice();
        }

        foreach ($order->getDeliveries() ?? new OrderDeliveryCollection() as $delivery) {
            $total += $delivery->getShippingCosts()->getTotalPrice();
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    /**
     * Builds a map of refunded quantities per order line item / delivery, keyed by its id.
     * The quantity is derived from the refunded amount (rounded up per unit), so a partial
     * amount of a single unit counts as one refunded unit and only rises once a further
     * unit's worth is refunded. Canceled and failed refunds are ignored, pending refunds
     * are included since those amounts can no longer be refunded again.
     *
     * @return array<string, int>
     */
    private function buildRefundedQuantities(OrderEntity $order, MollieRefundCollection $refunds): array
    {
        $refundedAmounts = $this->buildRefundedAmounts($order, $refunds);

        if (count($refundedAmounts) === 0) {
            return [];
        }

        $lineInfo = $this->buildLineInfo($order);

        $quantities = [];

        foreach ($refundedAmounts as $shopwareId => $amount) {
            if (! isset($lineInfo[$shopwareId])) {
                continue;
            }

            $quantities[$shopwareId] = $this->deriveRefundedUnits($amount, $lineInfo[$shopwareId]['max'], $lineInfo[$shopwareId]['quantity']);
        }

        return $quantities;
    }

    /**
     * Derives how many units of a line item are covered by the refunded amount, rounded up.
     * A partial amount of a single unit counts as one refunded unit; the result never
     * exceeds the line item quantity.
     */
    private function deriveRefundedUnits(float $refundedAmount, float $lineMax, int $quantity): int
    {
        if ($refundedAmount <= 0.0 || $lineMax <= 0.0 || $quantity <= 0) {
            return 0;
        }

        $units = (int) ceil(($refundedAmount - self::AMOUNT_TOLERANCE) * $quantity / $lineMax);

        return max(0, min($quantity, $units));
    }

    /**
     * Builds a map of the refunded amount per order line item / delivery, keyed by its id.
     * A refund item stores either full units (quantity > 0, amount is per unit) or a partial
     * remainder (quantity 0, amount is the total), so both cases are summed accordingly.
     * Canceled and failed refunds are ignored, pending refunds are included since those
     * amounts can no longer be refunded again.
     *
     * @return array<string, float>
     */
    private function buildRefundedAmounts(OrderEntity $order, MollieRefundCollection $refunds): array
    {
        $amounts = [];

        $dalRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        if (! $dalRefunds instanceof RefundCollection) {
            return $amounts;
        }

        /** @var RefundEntity $dalRefund */
        foreach ($dalRefunds as $dalRefund) {
            $mollieRefund = $refunds->findByMollieId((string) $dalRefund->getMollieRefundId());

            if ($mollieRefund === null) {
                continue;
            }

            $status = $mollieRefund->getStatus();
            if ($status === RefundStatus::Canceled || $status === RefundStatus::Failed) {
                continue;
            }

            foreach ($dalRefund->getRefundItems() as $refundItem) {
                $shopwareId = $refundItem->getOrderLineItemId() ?? $refundItem->getOrderDeliveryId();
                if ($shopwareId === null || $shopwareId === '') {
                    continue;
                }

                $quantity = $refundItem->getQuantity();
                $amount = $quantity > 0 ? $refundItem->getAmount() * $quantity : $refundItem->getAmount();

                $amounts[$shopwareId] = ($amounts[$shopwareId] ?? 0.0) + $amount;
            }
        }

        return $amounts;
    }

    /**
     * Caps each requested item's amount to the remaining refundable amount of its line item
     * (line total minus already refunded), so no single line can be over-refunded.
     *
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int, label?: string}> $items
     * @param array<string, float> $refundedPerLine
     * @param array<string, array{max: float, quantity: int}> $lineInfo
     *
     * @return array<array{id: string, quantity: int, amount: float, resetStock: int, label?: string}>
     */
    private function capItemsToLineMax(array $items, array $refundedPerLine, array $lineInfo): array
    {
        foreach ($items as $index => $item) {
            $lineId = (string) ($item['id'] ?? '');

            if (! isset($lineInfo[$lineId])) {
                continue;
            }

            $lineRemaining = max(0.0, round($lineInfo[$lineId]['max'] - ($refundedPerLine[$lineId] ?? 0.0), Mollie::ROUNDING_PRECISION));

            if ((float) ($item['amount'] ?? 0.0) > $lineRemaining) {
                $items[$index]['amount'] = $lineRemaining;
            }
        }

        return $items;
    }

    /**
     * Builds a map of the maximum refundable amount and quantity per order line item /
     * delivery, keyed by its id. The max is the line total (unit price * quantity); for net
     * orders the line tax is added on top, since the refund can include it.
     *
     * @return array<string, array{max: float, quantity: int}>
     */
    private function buildLineInfo(OrderEntity $order): array
    {
        $isGross = $order->getTaxStatus() === CartPrice::TAX_STATE_GROSS;

        $info = [];

        foreach (CartStruct::fromOrder($order)->jsonSerialize() as $cartItem) {
            $shopware = $cartItem->getShopware();

            if ($shopware->isPromotion()) {
                continue;
            }

            $lineMax = $shopware->getTotalPrice();
            if (! $isGross) {
                $lineMax += $shopware->getTax()->getTotalItemTax();
            }

            $info[$shopware->getId()] = [
                'max' => $lineMax,
                'quantity' => $shopware->getQuantity(),
            ];
        }

        return $info;
    }

    private function enrichRefundsWithComposition(MollieRefundCollection $mollieRefunds, OrderEntity $order): MollieRefundCollection
    {
        $dalRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        if (! $dalRefunds instanceof RefundCollection) {
            return $mollieRefunds;
        }

        /** @var RefundEntity $dalRefund */
        foreach ($dalRefunds as $dalRefund) {
            $mollieRefundId = (string) $dalRefund->getMollieRefundId();
            $mollieRefund = $mollieRefunds->findByMollieId($mollieRefundId);

            if ($mollieRefund === null) {
                continue;
            }

            $mollieRefund->setRefundItems($dalRefund->getRefundItems());
            $mollieRefund->setInternalDescription((string) $dalRefund->getInternalDescription());
        }

        return $mollieRefunds;
    }

    private function loadOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('currency');
        $criteria->addAssociation(OrderExtension::REFUND_PROPERTY_NAME . '.refundItems');
        $criteria->addAssociation('transactions.stateMachineState');

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (! $order instanceof OrderEntity) {
            throw new \RuntimeException(sprintf('Order "%s" not found', $orderId));
        }

        return $order;
    }

    private function extractMolliePayment(OrderEntity $order): Payment
    {
        $payment = $this->findMolliePayment($order);

        if (! $payment instanceof Payment) {
            throw new \RuntimeException(sprintf('No Mollie payment extension found for order "%s"', $order->getId()));
        }

        return $payment;
    }

    private function findMolliePayment(OrderEntity $order): ?Payment
    {
        $transaction = $this->transactionResolver->resolveRefundable($order);
        if ($transaction === null) {
            return null;
        }

        $payment = $transaction->getExtension(Mollie::EXTENSION);

        return $payment instanceof Payment ? $payment : null;
    }
}
