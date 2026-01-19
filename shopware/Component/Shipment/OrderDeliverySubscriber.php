<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OrderDeliverySubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository
     * @param EntityRepository<OrderLineItemCollection> $orderLineRepository
     */
    public function __construct(
        #[Autowire(service: 'order_delivery.repository')]
        private readonly EntityRepository $orderDeliveryRepository,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'order_line_item.repository')]
        private readonly EntityRepository $orderLineRepository,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
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

        $transitionName = $event->getTransition()->getTransitionName();
        if ($transitionName !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }
        // dump($event->getPreviousState()->getTechnicalName());
        $transition = $event->getTransition();
        $context = $event->getContext();

        $orderDeliveryId = $transition->getEntityId();

        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('order.lineItems');
        $criteria->getAssociation('order.transactions')->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING));

        $searchResult = $this->orderDeliveryRepository->search($criteria, $context);
        $orderDelivery = $searchResult->first();

        if (! $orderDelivery instanceof OrderDeliveryEntity) {
            $this->logger->error('Delivery not found for ' . $orderDeliveryId);

            return;
        }

        $order = $orderDelivery->getOrder();
        if ($order === null) {
            $this->logger->error('Order association missing for delivery ' . $orderDeliveryId);

            return;
        }

        $orderNumber = $order->getOrderNumber();

        if ($orderNumber === null) {
            $this->logger->error('Order number missing for order of delivery ' . $orderDeliveryId);

            return;
        }

        $salesChannelId = $order->getSalesChannelId();
        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);
        if (! $paymentSettings->isAutomaticShipment()) {
            return;
        }

        $transactions = $order->getTransactions();
        if ($transactions === null || $transactions->count() === 0) {
            $this->logger->debug('No transactions found for order ' . $orderNumber, [
                'orderDeliveryId' => $orderDeliveryId,
                'salesChannelId' => $salesChannelId,
            ]);

            return;
        }

        $firstTransaction = $transactions->first();
        if ($firstTransaction === null) {
            $this->logger->debug('No first transaction found for order ' . $orderNumber, [
                'orderDeliveryId' => $orderDeliveryId,
                'salesChannelId' => $salesChannelId,
            ]);

            return;
        }
        $payment = $firstTransaction->getExtension(Mollie::EXTENSION);
        $transactionId = $firstTransaction->getId();

        $logData = [
            'orderNumber' => $orderNumber,
            'orderDeliveryId' => $orderDeliveryId,
            'transactionId' => $transactionId,
            'salesChannelId' => $salesChannelId,
        ];

        if (! $payment instanceof Payment) {
            $this->logger->debug('Transaction was not paid with Mollie', $logData);

            return;
        }
        $paymentId = $payment->getId();

        $currency = $order->getCurrency();
        if ($currency === null) {
            $this->logger->error('Currency association missing for order ' . $orderNumber, $logData);

            return;
        }

        $money = Money::fromOrder($order, $currency);

        $payment = $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);

        $alreadyCaptured = $payment->getCapturedAmount();

        $orderShippedEvent = new OrderShippedEvent($transactionId, $context);

        if ($alreadyCaptured instanceof Money && $alreadyCaptured->getValue() >= $money->getValue()) {
            $this->logger->warning('Order already shipped', $logData);
            $this->eventDispatcher->dispatch($orderShippedEvent);

            return;
        }

        // throw new \Exception('stop here');

        $createCapture = new CreateCapture($money, 'automaticShipment');

        $capture = $this->mollieGateway->createCapture($createCapture, $paymentId, $orderNumber, $salesChannelId);

        $upsertArray = [];
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $upsertArray[] = [
                'id' => $lineItem->getId(),
                'customFields' => [
                    Mollie::EXTENSION => [
                        'captureId' => $capture->getId(),
                        'quantity' => $lineItem->getQuantity()
                    ]
                ]
            ];
        }

        if ($upsertArray !== []) {
            $this->orderLineRepository->upsert($upsertArray, $context);
        }

        $this->eventDispatcher->dispatch($orderShippedEvent);

        $this->logger->info('Order Shipped', $logData);
    }
}
