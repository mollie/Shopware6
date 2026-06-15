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
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
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
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/_action/mollie/ship', name: 'api.action.mollie.ship.order', methods: ['POST', 'GET'])]
    public function ship(Request $request, Context $context): ShipOrderResponse
    {
        $orderId = (string) $request->get('orderId');
        $orderId = strtolower($orderId);

        $items = $request->get('items');
        if (count($items) === 0) {
            throw ShippingException::noLineItems($orderId);
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.positions');
        $criteria->addAssociation('deliveries.shippingMethod');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (! $order instanceof OrderEntity) {
            throw ShippingException::orderNotFound($orderId);
        }

        $orderNumber = $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        if ($orderNumber === null) {
            throw ShippingException::orderNotFound($orderId);
        }
        $transactions = $order->getTransactions();
        if ($transactions === null || $transactions->count() === 0) {
            throw ShippingException::orderNotFound($orderId);
        }
        $firstTransaction = $transactions->first();
        if ($firstTransaction === null) {
            throw ShippingException::orderNotFound($orderId);
        }
        $payment = $firstTransaction->getExtension(Mollie::EXTENSION);
        if (! $payment instanceof Payment) {
            throw ShippingException::orderNotFound($orderId);
        }
        $deliveryCollection = $order->getDeliveries() ?? new OrderDeliveryCollection();
        $paymentId = $payment->getId();
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $currency = $order->getCurrency();
        if ($currency === null) {
            throw ShippingException::orderNotFound($orderId);
        }

        $orderShippedEvent = new OrderShippedEvent($firstTransaction->getId(), $context);
        $mollieOrderId = $payment->getOrderId();

        $shippingItems = new ShippingItemCollection();
        $lineUpserts = $this->collectLineItemUpserts($items, $lineItems, $orderId, $shippingItems);
        $deliveryUpserts = $this->collectDeliveryUpserts($lineUpserts, $shippingItems, $deliveryCollection);

        if ($mollieOrderId !== null) {
            $trackingCode = (string) $request->get('trackingCode', '');
            $lineItemIds = array_column($lineUpserts, 'id');
            $tracking = $this->resolveTracking($trackingCode, $deliveryCollection, $lineItemIds);
            $createShipment = new CreateShipment($shippingItems, $tracking);
            $shipment = $this->mollieGateway->createShipment($createShipment, $mollieOrderId, $orderNumber, $salesChannelId);

            return $this->persistAndDispatch($lineUpserts, $deliveryUpserts, $shipment->getId(), 'shipmentId', $orderId, $orderShippedEvent, $context);
        }

        $createCapture = new CreateCapture($shippingItems, $currency->getIsoCode());
        $capture = $this->mollieGateway->createCapture($createCapture, $paymentId, (string) $orderNumber, $salesChannelId);

        return $this->persistAndDispatch($lineUpserts, $deliveryUpserts, $capture->getId(), 'captureId', $orderId, $orderShippedEvent, $context);
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
                    Mollie::EXTENSION => ['quantity' => $newQuantity],
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
            $this->orderService->orderDeliveryStateTransition(
                $deliveryId,
                StateMachineTransitionActions::ACTION_SHIP_PARTIALLY,
                new ParameterBag(),
                $context
            );
        }

        $this->eventDispatcher->dispatch($orderShippedEvent);

        return new ShipOrderResponse($mollieId, $orderId, $lineUpserts);
    }

    /**
     * @param list<string> $targetLineItemIds
     */
    private function resolveTracking(string $requestCode, OrderDeliveryCollection $deliveries, array $targetLineItemIds): ?Tracking
    {
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
}
