<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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

        // Nothing left to ship: treat as an idempotent no-op so repeated/automatic shipment calls don't fail.
        if (count($items) === 0) {
            $this->logger->info('ShipOrderRoute: nothing to ship, order is already fully shipped or cancelled', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

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

        // Only an order whose current payment is authorized (manual capture / pay-later) can be shipped:
        // a paid payment is already captured and a fully handled order has nothing left, so both the
        // Payments API capture and the Orders API shipment would fail at Mollie. Treat anything else as
        // an idempotent no-op and, importantly, do not fire the OrderShippedEvent when nothing is shipped.
        $currentState = $currentTransaction?->getStateMachineState();
        if ($currentTransaction === null || $currentState === null || $currentState->getTechnicalName() !== OrderTransactionStates::STATE_AUTHORIZED) {
            $this->logger->info('ShipOrderRoute: no capturable authorized payment, nothing to ship', $logContext);

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

        $orderShippedEvent = new OrderShippedEvent($currentTransaction->getId(), $context);
        $mollieOrderId = $payment->getOrderId();

        $shippingItems = new ShippingItemCollection();
        $lineUpserts = $this->collectLineItemUpserts($items, $lineItems, $orderId, $shippingItems);
        $deliveryUpserts = $this->collectDeliveryUpserts($lineUpserts, $shippingItems, $deliveryCollection);
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

            $shipment = $this->mollieGateway->createShipment($createShipment, $mollieOrderId, $orderNumber, $salesChannelId);

            $logContext['mollieShipmentId'] = $shipment->getId();

            $this->logger->info('ShipOrderRoute: Mollie createShipment response', $logContext);

            return $this->persistAndDispatch($lineUpserts, $deliveryUpserts, $shipment->getId(), 'shipmentId', $orderId, $orderShippedEvent, $fullyShipped, $context);
        }

        $createCapture = new CreateCapture($shippingItems, $currency->getIsoCode());

        $logContext['molliePaymentId'] = $paymentId;

        $this->logger->info('ShipOrderRoute: calling Mollie createCapture (Payments API)', $logContext);

        $capture = $this->mollieGateway->createCapture($createCapture, $paymentId, (string) $orderNumber, $salesChannelId);

        $logContext['mollieCaptureId'] = $capture->getId();

        $this->logger->info('ShipOrderRoute: Mollie createCapture response', $logContext);

        if ($fullyShipped && $this->hasCancelledItems($lineItems)) {
            $this->logger->info('ShipOrderRoute: all items handled with cancellations, releasing authorization (Payments API)', $logContext);
            $this->mollieGateway->releaseAuthorization($paymentId, (string) $orderNumber, $salesChannelId);
        }

        return $this->persistAndDispatch($lineUpserts, $deliveryUpserts, $capture->getId(), 'captureId', $orderId, $orderShippedEvent, $fullyShipped, $context);
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
    private function collectLineItemUpserts(array $items, OrderLineItemCollection $lineItems, string $orderId, ShippingItemCollection $shippingItems): array
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

            $shippingItem = new ShippingItem(
                $requestedQuantity,
                $requestedQuantity . 'x ' . $name,
                $lineItem->getUnitPrice() * $requestedQuantity,
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
    private function collectDeliveryUpserts(array $lineUpserts, ShippingItemCollection $shippingItems, OrderDeliveryCollection $deliveryCollection): array
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

            $shippingItem = new ShippingItem(
                $shippingCostsQuantity,
                $shippingCostsQuantity . 'x ' . $shippingMethod->getName(),
                $shippingCosts->getUnitPrice() * $shippingCostsQuantity,
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
