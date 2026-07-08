<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Subscriber;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolver;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolverInterface;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CancelOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: OrderTransactionResolver::class)]
        private OrderTransactionResolverInterface $transactionResolver,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order.state_changed' => 'onOrderStateChanged',
        ];
    }

    public function onOrderStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        if ($event->getTransition()->getTransitionName() !== StateMachineTransitionActions::ACTION_CANCEL) {
            return;
        }

        $orderId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');

        /** @var ?OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (! $order instanceof OrderEntity) {
            return;
        }

        $salesChannelId = $order->getSalesChannelId();
        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);

        if (! $paymentSettings->isAutomaticCancellation()) {
            return;
        }

        // Only cancel when the order's current payment is a Mollie payment. Shopware treats the first
        // non-cancelled/failed transaction as the current one, not necessarily the newest, so we resolve
        // the effective transaction instead of taking the newest by createdAt.
        $transaction = $this->transactionResolver->resolveEffective($order);
        if (! $transaction instanceof OrderTransactionEntity) {
            return;
        }

        /** @var ?Payment $molliePayment */
        $molliePayment = $transaction->getExtension(Mollie::EXTENSION);
        if (! $molliePayment instanceof Payment) {
            return;
        }

        $orderNumber = (string) $order->getOrderNumber();

        try {
            $mollieOrderId = $molliePayment->getOrderId();

            if ($mollieOrderId !== null) {
                $cancelledOrder = $this->mollieGateway->cancelOrder($mollieOrderId, $orderNumber, $salesChannelId);
                $this->logger->info('Auto-cancelled Mollie order', ['mollieOrderId' => $mollieOrderId, 'orderNumber' => $orderNumber, 'mollieStatus' => $cancelledOrder->getStatus()?->value]);

                return;
            }

            $molliePaymentId = $molliePayment->getId();
            $this->mollieGateway->cancelPayment($molliePaymentId, $orderNumber, $salesChannelId);
            $this->logger->info('Auto-cancelled Mollie payment', ['molliePaymentId' => $molliePaymentId, 'orderNumber' => $orderNumber]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to auto-cancel Mollie order/payment', [
                'orderNumber' => $orderNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
