<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Mollie\Tracking;
use Mollie\Shopware\Component\Shipment\AuthorizationReconciler;
use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Component\Shipment\ShipmentPersister;
use Mollie\Shopware\Component\Shipment\ShipmentTrackingResolver;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class ShipOrderRoute extends AbstractShipOrderRoute
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: ShipmentItemResolver::class)]
        private readonly ShipmentItemResolver $itemResolver,
        #[Autowire(service: ShipmentTrackingResolver::class)]
        private readonly ShipmentTrackingResolver $trackingResolver,
        #[Autowire(service: AuthorizationReconciler::class)]
        private readonly AuthorizationReconciler $reconciler,
        #[Autowire(service: ShipmentPersister::class)]
        private readonly ShipmentPersister $persister,
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
        $items = $this->itemResolver->normalizeItems($request->get('items'));

        $logContext = [
            'orderNumber' => $orderNumber,
            'orderId' => $orderId,
            'requestedItems' => $items,
        ];

        $this->logger->info('ShipOrderRoute: request received', $logContext);

        $order = $this->loadOrder($orderId, $orderNumber, $context);

        $orderId = $order->getId();
        $orderNumber = $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        if ($orderNumber === null) {
            throw ShippingException::orderNotFound($orderId);
        }

        // When no specific items are requested, ship everything that is still open.
        if (count($items) === 0) {
            $items = $this->itemResolver->buildRemainingItems($order);
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
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $currency = $order->getCurrency();
        if ($currency === null) {
            throw ShippingException::orderNotFound($orderId);
        }
        $taxStatus = (string) $order->getTaxStatus();

        $orderShippedEvent = new OrderShippedEvent($currentTransaction->getId(), $context);
        $mollieOrderId = $payment->getOrderId();

        if ($nothingToShip) {
            return $this->reconciler->reconcileAuthorizedRemainder($order, $payment, $currency, $taxStatus, (string) $orderNumber, $salesChannelId, $mollieOrderId, $deliveryCollection, $lineItems, $logContext);
        }

        $shippingItems = new ShippingItemCollection();
        $lineUpserts = $this->itemResolver->collectLineItemUpserts($items, $lineItems, $orderId, $shippingItems, $currency, $taxStatus);
        $deliveryUpserts = $this->itemResolver->collectDeliveryUpserts($lineUpserts, $shippingItems, $deliveryCollection, $currency, $taxStatus);
        $fullyShipped = $this->itemResolver->isFullyShipped($lineItems, $lineUpserts);

        $orderShippedEvent->setShippingItems($shippingItems);

        $logContext['lineUpserts'] = $lineUpserts;
        $logContext['deliveryUpsertsCount'] = count($deliveryUpserts);
        $logContext['fullyShipped'] = $fullyShipped;
        $logContext['shippingItems'] = json_encode($shippingItems);

        $this->logger->info('ShipOrderRoute: collected shipping data', $logContext);

        // The Orders API is line-item based and captures on shipment; the Payments API needs an
        // explicit capture. Decide here which one applies and delegate the Mollie call.
        if ($mollieOrderId !== null) {
            $lineItemIds = array_column($lineUpserts, 'id');
            $tracking = $this->trackingResolver->resolve($request, $deliveryCollection, $lineItemIds);
            $orderShippedEvent->setTracking($tracking);

            $mollieId = $this->shipViaOrdersApi($shippingItems, $tracking, $mollieOrderId, (string) $orderNumber, $salesChannelId, $logContext);
            $mollieIdKey = 'shipmentId';
        } else {
            $mollieId = $this->reconciler->captureViaPaymentsApi($payment, $shippingItems, $order, $lineItems, $currency, (string) $orderNumber, $salesChannelId, $fullyShipped, $logContext);
            $mollieIdKey = 'captureId';
        }

        // The Mollie call failed and was swallowed (best-effort); do not touch the delivery state.
        if ($mollieId === null) {
            return new ShipOrderResponse('', $orderId, []);
        }

        return $this->persister->persist($lineUpserts, $deliveryUpserts, $mollieId, $mollieIdKey, $orderId, $orderShippedEvent, $fullyShipped, $context);
    }

    private function loadOrder(string $orderId, string $orderNumber, Context $context): OrderEntity
    {
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

        return $order;
    }

    /**
     * Ships via the Mollie Orders API (line-item based, captures on shipment). Returns the Mollie
     * shipment id, or null when the call failed (best-effort: the delivery state change must not be
     * interrupted).
     *
     * @param array<string, mixed> $logContext
     */
    private function shipViaOrdersApi(ShippingItemCollection $shippingItems, ?Tracking $tracking, string $mollieOrderId, string $orderNumber, string $salesChannelId, array $logContext): ?string
    {
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

            return null;
        }

        $logContext['mollieShipmentId'] = $shipment->getId();

        $this->logger->info('ShipOrderRoute: Mollie createShipment response', $logContext);

        return $shipment->getId();
    }
}
