<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\ShippingItem;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Mollie\Tracking;
use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Mollie\Shopware\Component\Transaction\Event\RepairLegacyTransactionEvent;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class ShipOrderRoute extends AbstractShipOrderRoute
{
    /**
     * Sub-cent tolerance for reconciliation amount comparisons (capture top-up / release decision).
     */
    private const RECONCILE_THRESHOLD = 0.005;

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<OrderLineItemCollection> $orderLineRepository
     * @param EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: 'order_line_item.repository')]
        private readonly EntityRepository $orderLineRepository,
        #[Autowire(service: 'order_delivery.repository')]
        private readonly EntityRepository $orderDeliveryRepository,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        private readonly OrderService $orderService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/_action/mollie/ship', name: 'api.action.mollie.ship.order', methods: ['POST', 'GET'])]
    public function ship(Request $request, Context $context): ShipOrderResponse
    {
        $orderId = strtolower((string) $request->get('orderId'));
        $orderNumber = (string) $request->get('orderNumber', '');
        $items = $this->normalizeItems($request->get('items'));

        $logContext = [
            'orderNumber' => $orderNumber,
            'orderId' => $orderId,
            'requestedItems' => $items,
        ];

        $this->logger->info('ShipOrderRoute: request received', $logContext);

        $criteria = new Criteria();
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.positions');
        $criteria->addAssociation('deliveries.shippingMethod');

        if ($orderNumber !== '') {
            $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        } else {
            $criteria->setIds([$orderId]);
        }

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (! $order instanceof OrderEntity) {
            throw $orderNumber !== '' ? ShippingException::orderNumberNotFound($orderNumber) : ShippingException::orderNotFound($orderId);
        }

        $orderId = $order->getId();
        $orderNumber = $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        if ($orderNumber === null) {
            throw ShippingException::orderNotFound($orderId);
        }

        // When no specific items are requested, ship everything that is still open.
        if (count($items) === 0) {
            $items = $this->buildRemainingItems($order);
        }

        // Nothing left to ship in Shopware: this is not necessarily a no-op. Older orders were
        // captured with a too low (net) amount, so the shipped items may still owe their taxes at
        // Mollie. We keep flowing to resolve the Mollie payment and reconcile the open authorization
        // below instead of returning early here.
        $nothingToShip = count($items) === 0;

        $logContext['orderId'] = $orderId;
        $logContext['orderNumber'] = $orderNumber;
        $logContext['orderLineItems'] = array_map(
            static function (OrderLineItemEntity $li): array {
                return [
                    'id' => $li->getId(),
                    'label' => $li->getLabel(),
                    'quantity' => $li->getQuantity(),
                    'shippedQty' => (int) (($li->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0),
                ];
            },
            ($order->getLineItems() ?? new OrderLineItemCollection())->getElements()
        );

        $this->logger->info('ShipOrderRoute: order loaded', $logContext);

        $transactions = $order->getTransactions();
        if ($transactions === null || $transactions->count() === 0) {
            throw ShippingException::orderNotFound($orderId);
        }

        $mollieTransactions = new MollieOrderTransactionCollection($transactions);
        $currentTransaction = $mollieTransactions->getCurrentOrderTransaction();

        // We no longer gate on the payment being authorized: merchants may flip an authorized order to
        // "paid" themselves (for their ERP), and those orders must still be shipped. We only require a
        // current transaction here; whether it is actually a Mollie payment is decided below via the
        // Mollie payment extension, and the Mollie API call itself is wrapped so a failing shipment
        // (e.g. an already captured payment) never interrupts the delivery state change.
        if ($currentTransaction === null) {
            $this->logger->info('ShipOrderRoute: no current transaction, nothing to ship', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $repairEvent = new RepairLegacyTransactionEvent($currentTransaction, $order, $context);
        $this->eventDispatcher->dispatch($repairEvent);

        $payment = $currentTransaction->getExtension(Mollie::EXTENSION);
        if (! $payment instanceof Payment) {
            // Not a Mollie order (or legacy data could not be repaired): there is nothing to ship at
            // Mollie. Treated as an idempotent no-op so headless callers and the automatic-shipment
            // subscriber don't run into an error.
            $this->logger->info('ShipOrderRoute: transaction has no Mollie payment, nothing to ship', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }
        $deliveryCollection = $order->getDeliveries() ?? new OrderDeliveryCollection();
        $paymentId = $payment->getId();
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $currency = $order->getCurrency();
        if ($currency === null) {
            throw ShippingException::orderNotFound($orderId);
        }
        $taxStatus = (string) $order->getTaxStatus();

        $orderShippedEvent = new OrderShippedEvent($currentTransaction->getId(), $context);
        $mollieOrderId = $payment->getOrderId();

        if ($nothingToShip) {
            return $this->reconcileAuthorizedRemainder($order, $payment, $currency, $taxStatus, (string) $orderNumber, $salesChannelId, $mollieOrderId, $deliveryCollection, $lineItems, $logContext);
        }

        $shippingItems = new ShippingItemCollection();
        $lineUpserts = $this->collectLineItemUpserts($items, $lineItems, $orderId, $shippingItems, $currency, $taxStatus);
        $deliveryUpserts = $this->collectDeliveryUpserts($lineUpserts, $shippingItems, $deliveryCollection, $currency, $taxStatus);
        $fullyShipped = $this->isFullyShipped($lineItems, $lineUpserts);

        $orderShippedEvent->setShippingItems($shippingItems);

        $logContext['lineUpserts'] = $lineUpserts;
        $logContext['deliveryUpsertsCount'] = count($deliveryUpserts);
        $logContext['fullyShipped'] = $fullyShipped;
        $logContext['shippingItems'] = json_encode($shippingItems);

        $this->logger->info('ShipOrderRoute: collected shipping data', $logContext);

        if ($mollieOrderId !== null) {
            $lineItemIds = array_column($lineUpserts, 'id');
            $tracking = $this->resolveTracking($request, $deliveryCollection, $lineItemIds);
            $orderShippedEvent->setTracking($tracking);
            $createShipment = new CreateShipment($shippingItems, $tracking);

            $logContext['mollieOrderId'] = $mollieOrderId;
            $logContext['tracking'] = $tracking !== null ? ['carrier' => $tracking->getCarrier(), 'code' => $tracking->getCode()] : null;

            $this->logger->info('ShipOrderRoute: calling Mollie createShipment (Orders API)', $logContext);

            try {
                $shipment = $this->mollieGateway->createShipment($createShipment, $mollieOrderId, $orderNumber, $salesChannelId);
            } catch (\Throwable $exception) {
                // Shipping at Mollie may fail (e.g. the payment was already captured because the merchant
                // set the order to paid manually). This must not interrupt the delivery state change, so we
                // only log the error and stop here.
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('ShipOrderRoute: Mollie createShipment failed, skipping shipment', $logContext);

                return new ShipOrderResponse('', $orderId, []);
            }

            $logContext['mollieShipmentId'] = $shipment->getId();

            $this->logger->info('ShipOrderRoute: Mollie createShipment response', $logContext);

            return $this->persistAndDispatch($lineUpserts, $deliveryUpserts, $shipment->getId(), 'shipmentId', $orderId, $orderShippedEvent, $fullyShipped, $context);
        }

        // Each shipment captures the gross amount of exactly its own items (incl. their taxes).
        $createCapture = new CreateCapture($shippingItems, $currency->getIsoCode());

        $hasCancelledItems = $this->hasCancelledItems($lineItems);

        // Capture the rounding difference once, on the first shipment (alongside the shipping costs).
        // It is stored on the order at payment creation (Shopware allows 4 decimals per currency while
        // Mollie allows only 2) and is never a Shopware line item. Orders created before this was
        // persisted fall back to the value on the Mollie payment. It is folded into the (larger,
        // positive) capture amount, so a negative difference only makes the capture a cent smaller -
        // no negative amount is ever sent, and the captured total lands exactly on the order total.
        // With cancellations it stays in the released remainder instead.
        if (! $hasCancelledItems && ! $this->hasPriorShipments($lineItems)) {
            $mollieCustomFields = $order->getCustomFields()[Mollie::EXTENSION] ?? [];
            $roundingDiff = array_key_exists('rounding_diff', $mollieCustomFields)
                ? (float) $mollieCustomFields['rounding_diff']
                : $this->resolveRoundingDifference($paymentId, (string) $orderNumber, $salesChannelId, $logContext);

            if (abs($roundingDiff) > self::RECONCILE_THRESHOLD) {
                $adjustedAmount = new Money($shippingItems->getTotalAmount() + $roundingDiff, $currency->getIsoCode());
                $createCapture->setAmount($adjustedAmount);
            }
        }

        $logContext['molliePaymentId'] = $paymentId;

        $this->logger->info('ShipOrderRoute: calling Mollie createCapture (Payments API)', $logContext);

        try {
            $capture = $this->mollieGateway->createCapture($createCapture, $paymentId, (string) $orderNumber, $salesChannelId);
        } catch (\Throwable $exception) {
            // Capturing at Mollie may fail (e.g. the payment was already captured because the merchant
            // set the order to paid manually). This must not interrupt the delivery state change, so we
            // only log the error and stop here.
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('ShipOrderRoute: Mollie createCapture failed, skipping shipment', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        // With cancellations the shipped items are captured above and the rest of the authorization
        // (cancelled items + rounding difference) is released so it is not charged to the customer.
        // Releasing is best-effort and asynchronous, so a failure must not undo the successful capture.
        if ($fullyShipped && $hasCancelledItems) {
            try {
                $this->logger->info('ShipOrderRoute: order fully handled with cancellations, releasing remaining authorization (Payments API)', $logContext);
                $this->mollieGateway->releaseAuthorization($paymentId, (string) $orderNumber, $salesChannelId);
            } catch (\Throwable $exception) {
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('ShipOrderRoute: releasing authorization failed', $logContext);
            }
        }

        $logContext['mollieCaptureId'] = $capture->getId();

        $this->logger->info('ShipOrderRoute: Mollie createCapture response', $logContext);

        return $this->persistAndDispatch($lineUpserts, $deliveryUpserts, $capture->getId(), 'captureId', $orderId, $orderShippedEvent, $fullyShipped, $context);
    }

    /**
     * Reconciles an order that has nothing left to ship in Shopware but still has an open Mollie
     * authorization (Payments API). This covers older orders captured with a too low (net) amount and
     * the rounding-difference line that never exists as a Shopware line item: the shipped items are
     * topped up to their gross amount, and any authorization beyond the shipped gross (cancelled
     * items, rounding difference) is released so it is not charged to the customer.
     *
     * @param array<string, mixed> $logContext
     */
    private function reconcileAuthorizedRemainder(
        OrderEntity $order,
        Payment $payment,
        CurrencyEntity $currency,
        string $taxStatus,
        string $orderNumber,
        string $salesChannelId,
        ?string $mollieOrderId,
        OrderDeliveryCollection $deliveryCollection,
        OrderLineItemCollection $lineItems,
        array $logContext
    ): ShipOrderResponse {
        $orderId = $order->getId();

        // The Orders API is line-item based; there is no single amount to top up here.
        if ($mollieOrderId !== null) {
            $this->logger->info('ShipOrderRoute: nothing to ship, order is already fully shipped or cancelled', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $paymentId = $payment->getId();

        try {
            $freshPayment = $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);
        } catch (\Throwable $exception) {
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('ShipOrderRoute: could not load Mollie payment for reconciliation', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $remaining = $freshPayment->getAmountRemaining();
        if ($remaining === null || $remaining->getValue() <= self::RECONCILE_THRESHOLD) {
            $this->logger->info('ShipOrderRoute: nothing to ship and no open authorization to reconcile', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $alreadyCaptured = $freshPayment->getCapturedAmount()?->getValue() ?? 0.0;
        $authorized = $freshPayment->getAmount()?->getValue() ?? 0.0;

        // Without cancellations the whole order was shipped, so the full authorized amount (incl. taxes
        // and rounding difference) is owed. With cancellations only the shipped items are owed; the
        // rest is released below.
        $target = $this->hasCancelledItems($lineItems)
            ? $this->sumShippedGross($lineItems, $deliveryCollection, $currency, $taxStatus)
            : $authorized;

        $shortfall = $target - $alreadyCaptured;
        $mollieId = '';

        // Top up the capture so the shipped items are fully captured incl. their taxes/rounding.
        if ($shortfall > self::RECONCILE_THRESHOLD) {
            $emptyItems = new ShippingItemCollection();
            $reconcileCapture = new CreateCapture($emptyItems, $currency->getIsoCode());
            $shortfallAmount = new Money($shortfall, $currency->getIsoCode());
            $reconcileCapture->setAmount($shortfallAmount);
            $reconcileCapture->setDescription(sprintf('Tax reconciliation for order %s', $orderNumber));

            try {
                $capture = $this->mollieGateway->createCapture($reconcileCapture, $paymentId, $orderNumber, $salesChannelId);
                $mollieId = $capture->getId();
                $logContext['reconciledAmount'] = $shortfall;
                $this->logger->info('ShipOrderRoute: reconciled missing amount via capture', $logContext);
            } catch (\Throwable $exception) {
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('ShipOrderRoute: reconciliation capture failed', $logContext);

                return new ShipOrderResponse('', $orderId, []);
            }
        }

        // Release the authorization that exceeds the target (cancelled items), so Mollie can settle the
        // payment to paid and the customer is not charged for it.
        if ($authorized - $target > self::RECONCILE_THRESHOLD) {
            try {
                $this->mollieGateway->releaseAuthorization($paymentId, $orderNumber, $salesChannelId);
                if ($mollieId === '') {
                    $mollieId = $paymentId;
                }
            } catch (\Throwable $exception) {
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('ShipOrderRoute: releasing authorization during reconciliation failed', $logContext);
            }
        }

        if ($mollieId === '') {
            $this->logger->info('ShipOrderRoute: nothing to reconcile', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        return new ShipOrderResponse($mollieId, $orderId, []);
    }

    /**
     * Sums the gross amount of everything that has already been shipped (line items and shipping
     * costs), using LineItem's net->gross normalization so it matches the amount that should have
     * been captured.
     */
    private function sumShippedGross(OrderLineItemCollection $lineItems, OrderDeliveryCollection $deliveryCollection, CurrencyEntity $currency, string $taxStatus): float
    {
        $total = 0.0;

        foreach ($lineItems as $lineItem) {
            $shippedQuantity = (int) (($lineItem->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0);
            if ($shippedQuantity <= 0) {
                continue;
            }
            $grossLine = LineItem::fromOrderLine($lineItem, $currency, $taxStatus);
            $total += $grossLine->getUnitPrice()->getValue() * $shippedQuantity;
        }

        foreach ($deliveryCollection as $delivery) {
            $shippedQuantity = (int) (($delivery->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0);
            if ($shippedQuantity <= 0 || $delivery->getShippingMethod() === null) {
                continue;
            }
            $grossDelivery = LineItem::fromDelivery($delivery, $currency, $taxStatus);
            $total += $grossDelivery->getUnitPrice()->getValue() * $shippedQuantity;
        }

        return $total;
    }

    /**
     * The rounding difference tracked on the Mollie payment lines (Shopware allows 4 decimals per
     * currency, Mollie only 2). Fallback for orders created before it was persisted on the order.
     * Best-effort: returns 0.0 when the payment cannot be loaded.
     *
     * @param array<string, mixed> $logContext
     */
    private function resolveRoundingDifference(string $paymentId, string $orderNumber, string $salesChannelId, array $logContext): float
    {
        try {
            $payment = $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);

            return $payment->getRoundingDiff();
        } catch (\Throwable $exception) {
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('ShipOrderRoute: could not resolve rounding difference', $logContext);

            return 0.0;
        }
    }

    /**
     * Whether any line item has already been shipped in an earlier shipment. Used to capture
     * the rounding difference only once, on the first shipment.
     */
    private function hasPriorShipments(OrderLineItemCollection $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            if ((int) ($fields['quantity'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function hasCancelledItems(OrderLineItemCollection $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            if ((int) ($fields['cancelled_quantity'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: string, quantity: int}>
     */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($item['id'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * Builds the list of all items of an order that are not yet fully shipped or cancelled.
     *
     * @return list<array{id: string, quantity: int}>
     */
    private function buildRemainingItems(OrderEntity $order): array
    {
        $items = [];
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shipped = (int) ($fields['quantity'] ?? 0);
            $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);
            $remaining = $lineItem->getQuantity() - $shipped - $cancelled;

            if ($remaining > 0) {
                $items[] = [
                    'id' => $lineItem->getId(),
                    'quantity' => $remaining,
                ];
            }
        }

        return $items;
    }

    /**
     * @param list<array{id: string, quantity: int}> $items
     *
     * @return list<array{id: string, customFields: array<string, mixed>}>
     */
    private function collectLineItemUpserts(array $items, OrderLineItemCollection $lineItems, string $orderId, ShippingItemCollection $shippingItems, CurrencyEntity $currency, string $taxStatus): array
    {
        $lineUpserts = [];

        foreach ($items as $item) {
            $rawId = (string) $item['id'];
            $requestedQuantity = (int) $item['quantity'];

            $lineItem = $this->findLineItem($lineItems, $rawId);

            if (! $lineItem instanceof OrderLineItemEntity) {
                throw ShippingException::lineItemNotFound(strtolower($rawId), $orderId);
            }

            $oldState = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? ['quantity' => 0];
            $shippedQuantity = (int) ($oldState['quantity'] ?? 0);

            if ($lineItem->getQuantity() === $shippedQuantity) {
                throw ShippingException::lineItemAlreadyShipped($lineItem->getId(), $orderId);
            }

            $newQuantity = $shippedQuantity + $requestedQuantity;

            if ($newQuantity > $lineItem->getQuantity()) {
                throw ShippingException::shippingQuantityTooHigh($lineItem->getId(), $orderId, $newQuantity, $lineItem->getQuantity());
            }

            $product = $lineItem->getProduct();
            $name = $product !== null ? (string) $product->getName() : (string) $lineItem->getLabel();
            $mollieLineId = ($lineItem->getCustomFields()[Mollie::EXTENSION] ?? [])['order_line_id'] ?? null;

            // Reuse LineItem's net->gross normalization so the capture amount matches the amount sent
            // at payment creation; getUnitPrice() alone is net for net-tax orders.
            $grossLine = LineItem::fromOrderLine($lineItem, $currency, $taxStatus);
            $shippingItem = new ShippingItem(
                $requestedQuantity,
                $requestedQuantity . 'x ' . $name,
                $grossLine->getUnitPrice()->getValue() * $requestedQuantity,
                $mollieLineId !== null ? (string) $mollieLineId : null,
            );
            $shippingItems->add($shippingItem);

            $lineUpserts[] = [
                'id' => $lineItem->getId(),
                'customFields' => [
                    Mollie::EXTENSION => array_merge($oldState, ['quantity' => $newQuantity]),
                ],
            ];
        }

        return $lineUpserts;
    }

    /**
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     *
     * @return list<array{id: string, customFields: array<string, mixed>}>
     */
    private function collectDeliveryUpserts(array $lineUpserts, ShippingItemCollection $shippingItems, OrderDeliveryCollection $deliveryCollection, CurrencyEntity $currency, string $taxStatus): array
    {
        $deliveryUpserts = [];
        $targetLineItemIds = array_column($lineUpserts, 'id');

        foreach ($deliveryCollection as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $shippingCostsQuantity = $shippingCosts->getQuantity();
            $positions = $delivery->getPositions();
            if ($positions === null) {
                continue;
            }

            $oldState = $delivery->getCustomFields()[Mollie::EXTENSION] ?? ['quantity' => 0];
            if ($shippingCostsQuantity === (int) ($oldState['quantity'] ?? 0)) {
                continue;
            }

            $deliveryBelongsToItems = false;

            // A delivery belongs to our shipment if at least one of its positions references one of the resolved line item IDs
            foreach ($positions as $position) {
                if (in_array($position->getOrderLineItemId(), $targetLineItemIds, true)) {
                    $deliveryBelongsToItems = true;
                    break;
                }
            }

            if ($deliveryBelongsToItems === false) {
                continue;
            }

            $shippingMethod = $delivery->getShippingMethod();
            if ($shippingMethod === null) {
                continue;
            }

            $mollieLineId = ($delivery->getCustomFields()[Mollie::EXTENSION] ?? [])['order_line_id'] ?? null;

            // Reuse LineItem's net->gross normalization so shipping costs are captured gross for
            // net-tax orders, consistent with the payment payload.
            $grossDelivery = LineItem::fromDelivery($delivery, $currency, $taxStatus);
            $shippingItem = new ShippingItem(
                $shippingCostsQuantity,
                $shippingCostsQuantity . 'x ' . $shippingMethod->getName(),
                $grossDelivery->getUnitPrice()->getValue() * $shippingCostsQuantity,
                $mollieLineId !== null ? (string) $mollieLineId : null,
            );
            $shippingItems->add($shippingItem);

            $deliveryUpserts[] = [
                'id' => $delivery->getId(),
                'customFields' => [
                    Mollie::EXTENSION => ['quantity' => $shippingCostsQuantity],
                ],
            ];
        }

        return $deliveryUpserts;
    }

    /**
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     * @param list<array{id: string, customFields: array<string, mixed>}> $deliveryUpserts
     */
    private function persistAndDispatch(
        array $lineUpserts,
        array $deliveryUpserts,
        string $mollieId,
        string $mollieIdKey,
        string $orderId,
        OrderShippedEvent $orderShippedEvent,
        bool $fullyShipped,
        Context $context
    ): ShipOrderResponse {
        foreach ($lineUpserts as $i => $row) {
            $lineUpserts[$i]['customFields'][Mollie::EXTENSION][$mollieIdKey] = $mollieId;
        }

        $this->orderLineRepository->upsert($lineUpserts, $context);

        $deliveryIds = array_column($deliveryUpserts, 'id');
        $deliveryId = $deliveryIds[0] ?? null;

        if (\count($deliveryUpserts) > 0) {
            foreach ($deliveryUpserts as $i => $row) {
                $deliveryUpserts[$i]['customFields'][Mollie::EXTENSION][$mollieIdKey] = $mollieId;
            }

            $this->orderDeliveryRepository->upsert($deliveryUpserts, $context);
        }

        if ($deliveryId !== null) {
            $transition = $fullyShipped
                ? StateMachineTransitionActions::ACTION_SHIP
                : StateMachineTransitionActions::ACTION_SHIP_PARTIALLY;

            // The delivery may already be in the target state when this is triggered from a manual
            // delivery state change (OrderDeliverySubscriber); skip the redundant transition then.
            try {
                $this->orderService->orderDeliveryStateTransition(
                    $deliveryId,
                    $transition,
                    new ParameterBag(),
                    $context
                );
            } catch (IllegalTransitionException $exception) {
                $this->logger->info('ShipOrderRoute: delivery state transition skipped', [
                    'orderId' => $orderId,
                    'deliveryId' => $deliveryId,
                    'transition' => $transition,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->eventDispatcher->dispatch($orderShippedEvent);

        return new ShipOrderResponse($mollieId, $orderId, $lineUpserts);
    }

    /**
     * Resolves the tracking information for a shipment. Explicit carrier/code/url from the request
     * take precedence; otherwise carrier and url are derived from the order's shipping method.
     *
     * @param list<string> $targetLineItemIds
     */
    private function resolveTracking(Request $request, OrderDeliveryCollection $deliveries, array $targetLineItemIds): ?Tracking
    {
        $requestCarrier = (string) $request->get('trackingCarrier', '');
        $requestCode = (string) $request->get('trackingCode', '');
        $requestUrl = (string) $request->get('trackingUrl', '');

        if ($requestCarrier !== '') {
            return new Tracking($requestCarrier, $requestCode, $requestUrl);
        }

        foreach ($deliveries as $delivery) {
            $positions = $delivery->getPositions();
            if ($positions === null) {
                continue;
            }

            $belongs = false;
            foreach ($positions as $position) {
                if (in_array($position->getOrderLineItemId(), $targetLineItemIds, true)) {
                    $belongs = true;
                    break;
                }
            }

            if ($belongs === false) {
                continue;
            }

            $shippingMethod = $delivery->getShippingMethod();
            if ($shippingMethod === null) {
                continue;
            }

            $carrier = (string) $shippingMethod->getName();
            if ($carrier === '') {
                return null;
            }

            $code = $requestCode;
            if ($code === '') {
                $codes = array_values(array_filter($delivery->getTrackingCodes()));
                if (count($codes) !== 1) {
                    return null;
                }
                $code = $codes[0];
            }

            if (mb_strlen($code) > 99) {
                return null;
            }

            $urlTemplate = (string) $shippingMethod->getTrackingUrl();
            if (str_contains($urlTemplate, '%s%')) {
                $urlTemplate = '';
            }
            $url = $urlTemplate !== '' ? trim(sprintf($urlTemplate, $code)) : '';
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $url = '';
            }

            return new Tracking($carrier, $code, $url);
        }

        return null;
    }

    private function findLineItem(OrderLineItemCollection $lineItems, string $idOrProductNumber): ?OrderLineItemEntity
    {
        $direct = $lineItems->get(strtolower($idOrProductNumber));
        if ($direct instanceof OrderLineItemEntity) {
            return $direct;
        }

        return $lineItems->firstWhere(function (OrderLineItemEntity $product) use ($idOrProductNumber) {
            return $product->getProduct()?->getProductNumber() === $idOrProductNumber;
        });
    }

    /**
     * Checks whether all order line items are fully handled (shipped or cancelled) after the current batch.
     * Items in $lineUpserts carry the updated shipped quantity; all others are read from custom fields.
     *
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     */
    private function isFullyShipped(OrderLineItemCollection $lineItems, array $lineUpserts): bool
    {
        $upsertQuantities = [];
        foreach ($lineUpserts as $upsert) {
            $upsertQuantities[$upsert['id']] = (int) ($upsert['customFields'][Mollie::EXTENSION]['quantity'] ?? 0);
        }

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getQuantity() <= 0) {
                continue;
            }

            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shipped = $upsertQuantities[$lineItem->getId()] ?? (int) ($fields['quantity'] ?? 0);
            $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);

            if (($shipped + $cancelled) < $lineItem->getQuantity()) {
                return false;
            }
        }

        return true;
    }
}
