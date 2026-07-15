<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Shipment\Route\AbstractShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolver;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolverInterface;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
        #[Autowire(service: OrderTransactionResolver::class)]
        private readonly OrderTransactionResolverInterface $transactionResolver,
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
        $criteria->addAssociation('order.transactions.stateMachineState');

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

        $logArray = [
            'orderId' => $order->getId(),
            'orderNumber' => (string) $order->getOrderNumber(),
            'orderDeliveryId' => $orderDeliveryId,
            'salesChannelId' => $order->getSalesChannelId(),
        ];

        if (! $this->settingsService->getPaymentSettings($order->getSalesChannelId())->isAutomaticShipment()) {
            $this->logger->debug('Automatic shipment is disabled for the saleschannel',$logArray);

            return;
        }
        $this->logger->info('Starting automatic shipment', $logArray);

        // Shipping captures an authorized (manual capture / pay-later) payment. A paid payment is already
        // captured (nothing to do), so we only act on the authorized transaction that can still be captured.
        $capturable = $this->transactionResolver->resolveCapturableAuthorized($order);
        if ($capturable === null) {
            $this->logger->warning('Latest order transaction is not authorized',$logArray);

            return;
        }
        $logArray['latestTransactionId'] = $capturable->getId();
        // The authorized payment must be a Mollie payment, otherwise there is nothing to capture via Mollie.
        if (! $capturable->getExtension(Mollie::EXTENSION) instanceof Payment) {
            $this->logger->debug('Latest order transaction does not have Payment data',$logArray);

            return;
        }

        // Delegate to the central shipment route; without items it ships everything that is still open.
        // A failing shipment (e.g. a Mollie API error) must not break the admin delivery state change,
        // so any error is caught and logged instead of bubbling up into the state machine transition.
        try {
            $request = new Request([], ['orderId' => $order->getId()]);
            $this->shipOrderRoute->ship($request, $context);
        } catch (\Throwable $exception) {
            $logArray['exception'] = $exception->getMessage();
            $this->logger->error('Automatic shipment via Mollie failed',$logArray);
        }
    }
}
