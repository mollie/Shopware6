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
use Mollie\Shopware\Component\StateHandler\OrderStateHandler;
use Mollie\Shopware\Component\StateHandler\OrderStateHandlerInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;
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

        if (! $this->isLatestOrderTransaction($transactionId, $shopwareOrder)) {
            $this->logger->info('Webhook Process - Skipped, transaction is no longer the latest one of the order', $logData);

            return new WebhookResponse($payment);
        }

        $this->logger->info('Webhook Process - Start', $logData);
        $webhookEvent = new WebhookEvent($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookEvent);

        $this->updatePaymentStatus($payment, $transactionId, $orderNumber, $context);
        $this->updatePaymentMethod($payment, $orderNumber, $shopwareOrder->getSalesChannelId(), $context);
        $this->updateOrderStatus($payment, $shopwareOrder, $context);
        $this->updateDeliveryStatus($payment, $shopwareOrder, $context);

        $webhookStatusEventClass = $payment->getStatus()->getWebhookEventClass();
        $webhookStatusEvent = new $webhookStatusEventClass($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookStatusEvent);
        $this->logger->info('Webhook Process - Finished', $logData);

        return new WebhookResponse($payment);
    }

    /**
     * The webhook url is bound to a single order transaction. When a payment is cancelled and the order
     * is finished with another (possibly non-Mollie) transaction, Shopware creates a newer transaction.
     * Calling the old transaction's webhook must not touch the order, payment or delivery state anymore,
     * so we only continue when the webhook transaction is still the latest one of the order.
     */
    private function isLatestOrderTransaction(string $transactionId, OrderEntity $shopwareOrder): bool
    {
        $transactions = $shopwareOrder->getTransactions();
        if ($transactions === null) {
            return true;
        }

        $latestTransaction = $transactions->first();
        if ($latestTransaction === null) {
            return true;
        }

        return $latestTransaction->getId() === $transactionId;
    }

    private function updatePaymentStatus(Payment $payment, string $transactionId, string $orderNumber, Context $context): void
    {
        $shopwareHandlerMethod = $payment->getStatus()->getShopwareHandlerMethod();
        $logData = [
            'transactionId' => $transactionId,
            'paymentStatus' => $payment->getStatus()->value,
            'shopwareMethod' => $shopwareHandlerMethod,
            'orderNumber' => $orderNumber,
        ];
        $this->logger->info('Change payment status', $logData);
        if (mb_strlen($shopwareHandlerMethod) === 0) {
            $this->logger->warning('Failed to find shopware handler method for status', $logData);

            return;
        }

        try {
            $this->stateMachineHandler->{$shopwareHandlerMethod}($transactionId, $context);
            $this->logger->info('Payment status changed', $logData);
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

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            StateMachineTransitionActions::ACTION_SHIP,
            new ParameterBag(),
            $context
        );
    }
}
