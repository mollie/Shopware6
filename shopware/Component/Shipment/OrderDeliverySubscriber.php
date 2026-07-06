<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Shipment\Route\AbstractShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

final class OrderDeliverySubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository
     */
    public function __construct(
        #[Autowire(service: 'order_delivery.repository')]
        private readonly EntityRepository $orderDeliveryRepository,
        #[Autowire(service: ShipOrderRoute::class)]
        private readonly AbstractShipOrderRoute $shipOrderRoute,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryChanged',
        ];
    }

    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        if ($event->getTransition()->getTransitionName() !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }

        $context = $event->getContext();
        $orderDeliveryId = $event->getTransition()->getEntityId();

        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order');
        $criteria->getAssociation('order.transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1)
        ;

        $orderDelivery = $this->orderDeliveryRepository->search($criteria, $context)->first();
        if (! $orderDelivery instanceof OrderDeliveryEntity) {
            $this->logger->error('Delivery not found for ' . $orderDeliveryId);

            return;
        }

        $order = $orderDelivery->getOrder();
        if ($order === null) {
            $this->logger->error('Order association missing for delivery ' . $orderDeliveryId);

            return;
        }

        if (! $this->settingsService->getPaymentSettings($order->getSalesChannelId())->isAutomaticShipment()) {
            return;
        }

        // Only ship via the Mollie API when the order's latest transaction is actually a Mollie payment.
        // Otherwise a delivery state change on a non-Mollie order would trigger an API call and fail.
        $latestTransaction = $order->getTransactions()?->first();
        if ($latestTransaction === null || ! $latestTransaction->getExtension(Mollie::EXTENSION) instanceof Payment) {
            return;
        }

        $this->logger->info('Starting automatic shipment for order: ' . (string) $order->getOrderNumber(), [
            'orderId' => $order->getId(),
            'orderDeliveryId' => $orderDeliveryId,
        ]);

        // Delegate to the central shipment route; without items it ships everything that is still open.
        $request = new Request([], ['orderId' => $order->getId()]);
        $this->shipOrderRoute->ship($request, $context);
    }
}
