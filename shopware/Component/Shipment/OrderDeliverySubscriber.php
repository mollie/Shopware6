<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Shipment\Route\AbstractShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
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
        $logArray = [
            'orderDeliveryId' => $orderDeliveryId,
        ];

        $this->logger->debug('Order delivery was shipped, checking automatic shipment', $logArray);

        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.transactions.stateMachineState');

        $orderDelivery = $this->orderDeliveryRepository->search($criteria, $context)->getEntities()->first();
        if (! $orderDelivery instanceof OrderDeliveryEntity) {
            $this->logger->debug('Order delivery not found, skipping automatic shipment', $logArray);

            return;
        }

        $order = $orderDelivery->getOrder();
        if ($order === null) {
            $this->logger->debug('Order delivery has no order, skipping automatic shipment', $logArray);

            return;
        }

        $logArray['orderId'] = $order->getId();
        $logArray['orderNumber'] = (string) $order->getOrderNumber();
        $logArray['salesChannelId'] = $order->getSalesChannelId();

        $transactions = MollieOrderTransactionCollection::fromOrder($order);
        $transaction = $transactions->getCurrentOrderTransaction();
        if (! $transaction instanceof OrderTransactionEntity) {
            $this->logger->debug('Order has no current transaction, skipping automatic shipment', $logArray);

            return;
        }

        $logArray['transactionId'] = $transaction->getId();

        $isMollieTransaction = $transaction->getExtension(Mollie::EXTENSION) instanceof Payment;
        $isLegacyMollieTransaction = isset($transaction->getCustomFields()[Mollie::EXTENSION]);
        if (! $isMollieTransaction && ! $isLegacyMollieTransaction) {
            $this->logger->debug('Order is not a Mollie order, skipping automatic shipment', $logArray);

            return;
        }

        if (! $this->settingsService->getPaymentSettings($order->getSalesChannelId())->isAutomaticShipment()) {
            $this->logger->debug('Automatic shipment is disabled for the saleschannel',$logArray);

            return;
        }
        $this->logger->info('Starting automatic shipment', $logArray);

        // All shipment logic (legacy repair, capturable/authorized check, Orders vs Payments API) lives in
        // the ship route, which is also used headless. This subscriber only reacts to the admin delivery
        // state change and delegates. A failing shipment (e.g. a Mollie API error) must not break the admin
        // state change, so any error is caught and logged instead of bubbling up into the state transition.
        try {
            $request = new Request([], ['orderId' => $order->getId()]);
            $this->shipOrderRoute->ship($request, $context);
        } catch (\Throwable $exception) {
            $logArray['exception'] = $exception->getMessage();
            $this->logger->error('Automatic shipment via Mollie failed',$logArray);
        }
    }
}
