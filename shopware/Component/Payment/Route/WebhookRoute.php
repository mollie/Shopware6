<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\PaymentMethodUpdater;
use Mollie\Shopware\Component\Payment\PaymentMethodUpdaterInterface;
use Mollie\Shopware\Component\Shipment\Route\AbstractShipOrderRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Component\StateHandler\OrderStateHandler;
use Mollie\Shopware\Component\StateHandler\OrderStateHandlerInterface;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolver;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolverInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class WebhookRoute extends AbstractWebhookRoute
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        private readonly OrderTransactionStateHandler $stateMachineHandler,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: PaymentMethodUpdater::class)]
        private readonly PaymentMethodUpdaterInterface $paymentMethodUpdater,
        #[Autowire(service: OrderStateHandler::class)]
        private readonly OrderStateHandlerInterface $orderStateHandler,
        private readonly OrderService $orderService,
        #[Autowire(service: OrderTransactionResolver::class)]
        private readonly OrderTransactionResolverInterface $transactionResolver,
        #[Autowire(service: ShipOrderRoute::class)]
        private readonly AbstractShipOrderRoute $shipOrderRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/webhook/{transactionId}', name: 'api.mollie.webhook', methods: ['GET', 'POST'])]
    public function notify(string $transactionId, Context $context): WebhookResponse
    {
        $transactionId = strtolower($transactionId);

        $logData = [
            'transactionId' => $transactionId,
        ];
        $this->logger->debug('Webhook Process - Requested', $logData);
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $shopwareOrder = $payment->getShopwareTransaction()->getOrder();
        if ($shopwareOrder === null) {
            $this->logger->error('Webhook Process Failed - Order not found');
            throw WebhookException::transactionWithoutOrder($transactionId);
        }
        $orderNumber = (string) $shopwareOrder->getOrderNumber();
        $logData['orderNumber'] = $orderNumber;

        $this->logger->info('Webhook Process - Start', $logData);
        $webhookEvent = new WebhookEvent($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookEvent);

        if ($this->shouldUpdatePaymentAndOrderStatus($payment, $shopwareOrder)) {
            $this->updatePaymentStatus($payment, $transactionId, $orderNumber, $context);
            $this->updatePaymentMethod($payment, $orderNumber, $shopwareOrder->getSalesChannelId(), $context);
            $this->updateOrderStatus($payment, $shopwareOrder, $context);
        } else {
            $this->logger->info('Webhook Process - Payment method, payment status and order status update skipped, order is already paid', $logData);
        }

        $this->updateDeliveryStatus($payment, $shopwareOrder, $context);
        $this->autoCaptureDigitalItems($payment, $shopwareOrder, $context);

        $webhookStatusEventClass = $payment->getStatus()->getWebhookEventClass();
        $webhookStatusEvent = new $webhookStatusEventClass($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookStatusEvent);
        $this->logger->info('Webhook Process - Finished', $logData);

        return new WebhookResponse($payment);
    }

    /**
     * The webhook url is bound to a single order transaction. Mollie keeps sending webhooks for that
     * transaction even after the order has been paid (e.g. a second payment attempt on the same order
     * or a late status re-sync). Once the order has a paid transaction we must not let a stale, lower
     * status downgrade it.
     *
     * Exceptions that still apply to an already paid order:
     * - refunds and chargebacks legitimately change a paid order;
     * - a second payment that also completes as "paid" is a new successful payment (e.g. the order was
     *   re-paid with another method), so it must overwrite the payment method, payment status and order
     *   status instead of being ignored.
     */
    private function shouldUpdatePaymentAndOrderStatus(Payment $payment, OrderEntity $shopwareOrder): bool
    {
        $status = $payment->getStatus();

        if ($status->isRefundRelated() || $status === PaymentStatus::PAID) {
            return true;
        }

        return ! $this->transactionResolver->hasPaidTransaction($shopwareOrder);
    }

    private function updatePaymentStatus(Payment $payment, string $transactionId, string $orderNumber, Context $context): void
    {
        $shopwareHandlerMethod = $payment->getStatus()->getShopwareHandlerMethod();
        $targetPaymentStatus = $payment->getStatus()->getShopwarePaymentStatus();
        $currentTransactionState = $payment->getShopwareTransaction()->getStateMachineState();
        $currentPaymentStatus = $currentTransactionState !== null ? $currentTransactionState->getTechnicalName() : '';

        $logData = [
            'transactionId' => $transactionId,
            'paymentStatus' => $payment->getStatus()->value,
            'shopwareMethod' => $shopwareHandlerMethod,
            'currentPaymentStatus' => $currentPaymentStatus,
            'targetPaymentStatus' => $targetPaymentStatus,
            'orderNumber' => $orderNumber,
        ];
        $this->logger->info('Change payment status', $logData);
        if (mb_strlen($shopwareHandlerMethod) === 0) {
            $this->logger->warning('Failed to find shopware handler method for status', $logData);

            return;
        }

        if ($currentPaymentStatus === $targetPaymentStatus) {
            $this->logger->debug('Payment status transition skipped, transaction is already in the target state', $logData);

            return;
        }

        try {
            $this->stateMachineHandler->{$shopwareHandlerMethod}($transactionId, $context);
            $this->logger->info('Payment status changed', $logData);
        } catch (IllegalTransitionException $exception) {
            $logData['exceptionMessage'] = $exception->getMessage();
            $this->logger->warning('Payment status transition failed, transaction is already in the target state or the state machine is misconfigured', $logData);
        } catch (\Throwable $exception) {
            $logData['exceptionMessage'] = $exception->getMessage();
            $this->logger->error('Failed to change payment status', $logData);
            throw WebhookException::paymentStatusChangeFailed($transactionId,$orderNumber,$exception);
        }
    }

    private function updatePaymentMethod(Payment $payment, string $orderNumber, string $salesChannelId, Context $context): void
    {
        $transaction = $payment->getShopwareTransaction();
        $transactionId = $transaction->getId();
        $transactionPaymentMethod = $transaction->getPaymentMethod();

        $molliePaymentMethod = $payment->getMethod();
        if ($molliePaymentMethod === null) {
            throw WebhookException::paymentWithoutMethod($transactionId, $payment->getId());
        }
        if ($transactionPaymentMethod === null) {
            throw WebhookException::transactionWithoutPaymentMethod($transactionId);
        }
        /** @var ?PaymentMethodExtension $molliePaymentMethodExtension */
        $molliePaymentMethodExtension = $transactionPaymentMethod->getExtension(Mollie::EXTENSION);

        if ($molliePaymentMethodExtension === null) {
            throw WebhookException::transactionWithoutMolliePayment($transactionId);
        }
        $logData = [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];
        try {
            $newPaymentMethodId = $this->paymentMethodUpdater->updatePaymentMethod($molliePaymentMethodExtension,$molliePaymentMethod,$transactionId,$orderNumber,$salesChannelId,$context);
            $logData['newPaymentMethodId'] = $newPaymentMethodId;
            $this->logger->info('Updated payment method id for transaction', $logData);
        } catch (\Throwable $exception) {
            $logData['exceptionMessage'] = $exception->getMessage();
            $this->logger->error('Failed to update payment method id for transaction', $logData);
            throw WebhookException::paymentMethodChangeFailed($transactionId,$orderNumber,$exception);
        }
    }

    private function updateOrderStatus(Payment $payment, OrderEntity $shopwareOrder, Context $context): void
    {
        $transaction = $payment->getShopwareTransaction();
        $transactionId = $transaction->getId();
        $paymentId = $payment->getId();
        $salesChannelId = $shopwareOrder->getSalesChannelId();
        $orderNumber = (string) $shopwareOrder->getOrderNumber();
        $paymentStatus = $payment->getStatus();
        $shopwarePaymentStatus = $payment->getStatus()->getShopwarePaymentStatus();
        $orderStateId = $shopwareOrder->getStateId();

        $currentOrderState = $shopwareOrder->getStateMachineState();
        if ($currentOrderState === null) {
            throw WebhookException::orderWithoutState($transactionId,$orderNumber);
        }

        $currentState = $currentOrderState->getTechnicalName();

        $logData = [
            'transactionId' => $transactionId,
            'paymentId' => $paymentId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'molliePaymentStatus' => $paymentStatus->value,
            'shopwarePaymentStatus' => $shopwarePaymentStatus,
            'currentOrderStateId' => $orderStateId,
            'currentState' => $currentState,
        ];

        try {
            $this->logger->info('Start - Change order status', $logData);

            $newOrderStateId = $this->orderStateHandler->performTransition($shopwareOrder,$shopwarePaymentStatus,$currentState, $salesChannelId, $context);

            $logData['newOrderStateId'] = $newOrderStateId;

            $this->logger->info('Finished - Change order status, successful', $logData);
        } catch (IllegalTransitionException $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->warning('Finished - Change order status transition failed, order is already in the target state or the state machine is misconfigured', $logData);
        } catch (\Throwable $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Finished - Change order status, Failed to change order status', $logData);
            throw WebhookException::orderStatusChangeFailed($transactionId, $orderNumber, $exception);
        }
    }

    private function updateDeliveryStatus(Payment $payment, OrderEntity $shopwareOrder, Context $context): void
    {
        $orderNumber = (string) $shopwareOrder->getOrderNumber();
        $captured = $payment->getCapturedAmount();
        $amount = $payment->getAmount();

        $this->logger->info('updateDeliveryStatus: checking capture amounts', [
            'orderNumber' => $orderNumber,
            'paymentStatus' => $payment->getStatus()->value,
            'capturedAmount' => $captured !== null ? $captured->getValue() : null,
            'totalAmount' => $amount !== null ? $amount->getValue() : null,
        ]);

        // A chargeback or refund no longer reports as PAID (the status is derived in
        // Payment::createFromClientResponse), so the PAID check already excludes those cases.
        if ($captured === null || (float) $captured->getValue() <= 0.0 || $payment->getStatus() !== PaymentStatus::PAID) {
            return;
        }

        // Only transition to fully shipped when the captured amount covers the full payment amount.
        // Partial captures are already handled by ShipOrderRoute (ACTION_SHIP_PARTIALLY).
        if ($amount === null || $captured->getValue() < $amount->getValue()) {
            return;
        }

        $deliveries = $shopwareOrder->getDeliveries();
        if ($deliveries === null) {
            return;
        }
        $firstDelivery = $deliveries->first();
        if ($firstDelivery === null) {
            return;
        }
        $orderDeliveryId = $firstDelivery->getId();

        // The delivery may already be shipped (e.g. via ShipOrderRoute or a manual state change);
        // skip the redundant transition then instead of failing the whole webhook.
        try {
            $this->orderService->orderDeliveryStateTransition(
                $orderDeliveryId,
                StateMachineTransitionActions::ACTION_SHIP,
                new ParameterBag(),
                $context
            );
        } catch (IllegalTransitionException $exception) {
            $this->logger->warning('Delivery status transition failed, delivery is already in the target state or the state machine is misconfigured', [
                'orderNumber' => $orderNumber,
                'orderDeliveryId' => $orderDeliveryId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Digital (downloadable) line items can never be shipped in Shopware, so no shipment event will ever
     * capture them for a manual capture / pay-later payment. As soon as such a payment is authorized we
     * capture the digital line items here. A digital-only order is captured in full (and reaches "paid"
     * via Mollie's follow-up webhook), while a mixed order is only captured partially - its physical part
     * is still captured on the real shipment, so we flag its delivery as partially shipped.
     */
    private function autoCaptureDigitalItems(Payment $payment, OrderEntity $shopwareOrder, Context $context): void
    {
        // Act on the fresh Mollie status: only an authorized payment can still be captured, and only
        // manual capture / pay-later methods (Klarna, Billie, Riverty) ever reach the authorized status,
        // so this status check implicitly limits the auto-capture to those methods. Relying on the
        // Shopware transaction state would be stale here, because it is only transitioned to authorized
        // earlier in this same webhook and the in-memory order still holds the old state.
        if ($payment->getStatus() !== PaymentStatus::AUTHORIZED) {
            return;
        }

        $digitalItems = $this->collectDigitalItems($shopwareOrder);
        if (count($digitalItems) === 0) {
            return;
        }

        $orderId = $shopwareOrder->getId();
        $logData = [
            'orderId' => $orderId,
            'orderNumber' => (string) $shopwareOrder->getOrderNumber(),
            'digitalItems' => $digitalItems,
        ];
        $this->logger->info('Auto-capturing digital line items for authorized order', $logData);

        // A failing capture must not break the webhook, so any error is caught and logged. A digital-only
        // order is captured in full and reaches "paid" via Mollie's follow-up webhook; a mixed order is
        // only captured partially and stays authorized until its physical part is shipped. The physical
        // delivery keeps its own state (open) - digital items are not part of any delivery.
        try {
            $request = new Request([], ['orderId' => $orderId, 'items' => $digitalItems]);
            $this->shipOrderRoute->ship($request, $context);
        } catch (\Throwable $exception) {
            $logData['error'] = $exception->getMessage();
            $this->logger->error('Auto-capture of digital line items failed', $logData);
        }
    }

    /**
     * @return list<array{id: string, quantity: int}>
     */
    private function collectDigitalItems(OrderEntity $shopwareOrder): array
    {
        $items = [];
        $lineItems = $shopwareOrder->getLineItems();
        if ($lineItems === null) {
            return $items;
        }

        foreach ($lineItems as $lineItem) {
            if (! in_array(State::IS_DOWNLOAD, $lineItem->getStates(), true)) {
                continue;
            }

            $items[] = [
                'id' => $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
            ];
        }

        return $items;
    }
}
