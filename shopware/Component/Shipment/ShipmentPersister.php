<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Shipment\Route\ShipOrderResponse;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Persists the outcome of a shipment: stamps the Mollie shipment/capture id onto the line-item and
 * delivery custom fields, transitions the delivery state (ship / ship partially) and dispatches the
 * OrderShippedEvent.
 */
final class ShipmentPersister
{
    /**
     * @param EntityRepository<OrderLineItemCollection> $orderLineRepository
     * @param EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository
     */
    public function __construct(
        #[Autowire(service: 'order_line_item.repository')]
        private readonly EntityRepository $orderLineRepository,
        #[Autowire(service: 'order_delivery.repository')]
        private readonly EntityRepository $orderDeliveryRepository,
        private readonly OrderService $orderService,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<array{id: string, customFields: array<string, mixed>}> $lineUpserts
     * @param list<array{id: string, customFields: array<string, mixed>}> $deliveryUpserts
     */
    public function persist(
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
                $this->logger->info('ShipmentPersister: delivery state transition skipped', [
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
}
