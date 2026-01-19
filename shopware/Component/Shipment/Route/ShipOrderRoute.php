<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
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

        $createCapture = new CreateCapture(new Money(0.0, $currency->getIsoCode()), '');

        $lineUpserts = $this->getLineItems($items, $lineItems, $orderId, $createCapture);

        $deliveryUpserts = $this->getDeliveries($lineUpserts, $createCapture, $deliveryCollection);

        $capture = $this->mollieGateway->createCapture($createCapture, $paymentId, (string) $orderNumber, $salesChannelId);

        foreach ($lineUpserts as $i => $row) {
            $lineUpserts[$i]['customFields'][Mollie::EXTENSION]['captureId'] = $capture->getId();
        }

        $this->orderLineRepository->upsert($lineUpserts, $context);

        $deliveryIds = array_column($deliveryUpserts, 'id');
        $deliveryId = $deliveryIds[0] ?? null;

        if (\count($deliveryUpserts) > 0) {
            foreach ($deliveryUpserts as $i => $row) {
                $deliveryUpserts[$i]['customFields'][Mollie::EXTENSION]['captureId'] = $capture->getId();
            }

            $this->orderDeliveryRepository->upsert($deliveryUpserts, $context);
        }

        if ($deliveryId !== null) {
            $transition = StateMachineTransitionActions::ACTION_SHIP_PARTIALLY;

            $this->orderService->orderDeliveryStateTransition(
                $deliveryId,
                $transition,
                new ParameterBag(),
                $context
            );
        }

        $this->eventDispatcher->dispatch($orderShippedEvent);

        return new ShipOrderResponse($capture->getId(), $orderId, $lineUpserts);
    }

    /**
     * Resolve an incoming identifier that can be either a real order line item ID or a product number.
     */
    private function findLineItem(OrderLineItemCollection $lineItems, string $idOrProductNumber): ?OrderLineItemEntity
    {
        // Try direct ID match first
        $direct = $lineItems->get(strtolower($idOrProductNumber));
        if ($direct instanceof OrderLineItemEntity) {
            return $direct;
        }

        return $lineItems->firstWhere(function (OrderLineItemEntity $product) use ($idOrProductNumber) {
            return $product->getProduct()?->getProductNumber() === $idOrProductNumber;
        });
    }

    /**
     * @param list<array{id: string, quantity: int}> $items
     *
     * @return list<array{id: string, customFields: array<string, mixed>}>
     */
    private function getLineItems(array $items, OrderLineItemCollection $lineItems, string $orderId, CreateCapture $createCapture): array
    {
        $captureAmount = (float) $createCapture->getMoney()->getValue();
        $descriptionArray = [];
        $lineUpserts = [];
        foreach ($items as $item) {
            $rawId = (string) $item['id'];
            $requestedQuantity = (int) $item['quantity'];

            $lineItem = $this->findLineItem($lineItems, $rawId);

            if (! $lineItem instanceof OrderLineItemEntity) {
                throw ShippingException::lineItemNotFound(strtolower($rawId), $orderId);
            }

            $oldCaptures = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [
                'quantity' => 0
            ];

            $product = $lineItem->getProduct();
            $name = $product !== null ? (string) $product->getName() : (string) $lineItem->getLabel();
            $descriptionArray[] = $requestedQuantity . 'x ' . $name;

            $quantity = (int) ($oldCaptures['quantity'] ?? 0);

            if ($lineItem->getQuantity() === $quantity) {
                throw ShippingException::lineItemAlreadyShipped($lineItem->getId(), $orderId);
            }

            $newQuantity = $quantity + $requestedQuantity;

            if ($newQuantity > $lineItem->getQuantity()) {
                throw ShippingException::shippingQuantityTooHigh($lineItem->getId(), $orderId, $newQuantity, $lineItem->getQuantity()); // message anpassen
            }

            $captureAmount += $lineItem->getUnitPrice() * $requestedQuantity;
            $lineUpserts[] = [
                'id' => $lineItem->getId(),
                'customFields' => [
                    Mollie::EXTENSION => [
                        'quantity' => $newQuantity
                    ],
                ],
            ];
        }

        $createCapture->setDescription(implode(', ', $descriptionArray));
        $createCapture->setMoney(new Money($captureAmount, $createCapture->getMoney()->getCurrency()));

        return $lineUpserts;
    }

    /**
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     *
     * @return list<array{id: string, customFields: array<string, mixed>}>
     */
    private function getDeliveries(array $lineUpserts, CreateCapture $createCapture, OrderDeliveryCollection $deliveryCollection): array
    {
        $descriptionArray = [];
        $deliveryUpserts = [];
        $targetLineItemIds = array_column($lineUpserts, 'id');

        $captureAmount = (float) $createCapture->getMoney()->getValue();
        foreach ($deliveryCollection as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $shippingMethod = $delivery->getShippingMethod();
            $shippingCostsQuantity = $shippingCosts->getQuantity();
            $positions = $delivery->getPositions();
            if ($positions === null) {
                continue;
            }

            $oldDeliveries = $delivery->getCustomFields()[Mollie::EXTENSION] ?? [
                'quantity' => 0
            ];

            $oldShippingCostsQuantity = (int) ($oldDeliveries['quantity'] ?? 0);

            if ($shippingCostsQuantity === $oldShippingCostsQuantity) {
                continue;
            }

            $deliveryBelongsToItems = false;

            // A delivery belongs to our shipment if at least one of its positions references one of the resolved line item IDs
            foreach ($positions as $position) {
                $posLineItemId = $position->getOrderLineItemId();
                if (in_array($posLineItemId, $targetLineItemIds, true)) {
                    $deliveryBelongsToItems = true;
                    break;
                }
            }

            if ($deliveryBelongsToItems === false) {
                continue;
            }

            if ($shippingMethod === null) {
                continue;
            }

            $captureAmount += ($shippingCosts->getUnitPrice() * $shippingCosts->getQuantity());
            $descriptionArray[] = $shippingCosts->getQuantity() . 'x ' . $shippingMethod->getName();

            $deliveryId = $delivery->getId();

            $deliveryUpserts[] = [
                'id' => $deliveryId,
                'customFields' => [
                    Mollie::EXTENSION => [
                        'quantity' => $shippingCosts->getQuantity()
                    ],
                ],
            ];
        }

        $createCapture->setDescription(implode(', ', $descriptionArray));
        $createCapture->setMoney(new Money($captureAmount, $createCapture->getMoney()->getCurrency()));

        return $deliveryUpserts;
    }
}
