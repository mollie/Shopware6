<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        $logData = [
            'transactionId' => $transactionId,
        ];
        $this->logger->info('Webhook route opened', $logData);
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $shopwareOrder = $payment->getShopwareTransaction()->getOrder();
        if ($shopwareOrder === null) {
            throw WebhookException::transactionWithoutOrder($transactionId);
        }
        $orderNumber = (string) $shopwareOrder->getOrderNumber();
        $webhookEvent = new WebhookEvent($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookEvent);

        $this->updatePaymentStatus($payment, $transactionId, $orderNumber, $context);
        $this->updatePaymentMethod($payment, $orderNumber, $shopwareOrder->getSalesChannelId(), $context);
        $this->updateOrderStatus($payment, $shopwareOrder, $context);

        $webhookStatusEventClass = $payment->getStatus()->getWebhookEventClass();
        $webhookStatusEvent = new $webhookStatusEventClass($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookStatusEvent);

        return new WebhookResponse($payment);
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
        $shopwarePaymentStatus = $paymentStatus->getShopwarePaymentStatus();
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
}
