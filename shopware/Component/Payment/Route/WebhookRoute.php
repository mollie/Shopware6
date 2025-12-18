<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\StateHandler\OrderStateHandler;
use Mollie\Shopware\Component\StateHandler\OrderStateHandlerInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class WebhookRoute extends AbstractWebhookRoute
{
    /**
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        private OrderTransactionStateHandler $stateMachineHandler,
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: OrderStateHandler::class)]
        private OrderStateHandlerInterface $orderStateHandler,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/webhook/{transactionId}', name: 'api.mollie.webhook', methods: ['GET', 'POST'])]
    public function notify(string $transactionId, Context $context): WebhookRouteResponse
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

        return new WebhookRouteResponse();
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
        } catch (IllegalTransitionException $exception) {
            $logData['exceptionMessage'] = $exception->getMessage();
            $this->logger->error('Failed to change payment status', $logData);
            throw $exception;
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
        $logData = [
            'transactionId' => $transactionId,
            'molliePaymentMethod' => $molliePaymentMethod->value,
            'orderNumber' => $orderNumber,
        ];

        $this->logger->info('Change payment method if changed', $logData);

        if ($transactionPaymentMethod === null) {
            throw WebhookException::transactionWithoutPaymentMethod($transactionId);
        }
        /** @var ?PaymentMethodExtension $molliePaymentMethodExtension */
        $molliePaymentMethodExtension = $transactionPaymentMethod->getExtension(Mollie::EXTENSION);

        if ($molliePaymentMethodExtension === null) {
            throw WebhookException::transactionWithoutMolliePayment($transactionId);
        }
        $logData['shopwarePaymentMethod'] = $molliePaymentMethodExtension->getPaymentMethod()->value;

        $this->logger->debug('Start to compare payment', $logData);

        if ($molliePaymentMethodExtension->getPaymentMethod() === $molliePaymentMethod) {
            $this->logger->debug('Payment methods are the same', $logData);

            return;
        }

        if ($molliePaymentMethodExtension->getPaymentMethod() === PaymentMethod::APPLEPAY && $molliePaymentMethod === PaymentMethod::CREDIT_CARD) {
            $this->logger->debug('Apple Pay payment methods are stored as credit card in mollie, no change needed', $logData);

            return;
        }

        $this->logger->debug('Payment methods are different, try to find payment method based on mollies payment method name', $logData);

        $newPaymentMethodId = $this->paymentMethodRepository->getIdByPaymentMethod($molliePaymentMethod, $salesChannelId, $context);

        if ($newPaymentMethodId === null) {
            throw WebhookException::paymentMethodNotFound($transactionId, $molliePaymentMethod->value);
        }

        $this->orderTransactionRepository->upsert([
            [
                'id' => $transactionId,
                'paymentMethodId' => $newPaymentMethodId
            ]
        ], $context);

        $this->logger->info('Changed payment methods for transaction', $logData);
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
        $logData = [
            'transactionId' => $transactionId,
            'paymentId' => $paymentId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'molliePaymentStatus' => $paymentStatus->value,
            'shopwarePaymentStatus' => $shopwarePaymentStatus,
            'currentOrderStateId' => $orderStateId,
        ];
        $this->logger->info('Change order status based on order status mapping', $logData);
        $orderStateSettings = $this->settingsService->getOrderStateSettings($salesChannelId);
        $finalOrderStateId = (string) $orderStateSettings->getFinalOrderState();

        $logData['finalOrderStateId'] = $finalOrderStateId;

        if ($finalOrderStateId === $orderStateId) {
            $this->logger->info('Order is in final state, changing skipped', $logData);

            return;
        }

        $transition = $orderStateSettings->getStatus($shopwarePaymentStatus);

        if ($transition === null) {
            $this->logger->info('Target order status is not configured for shopware payment status', $logData);

            return;
        }

        $logData['targetOrderStatus'] = $transition;
        $currentOrderState = $shopwareOrder->getStateMachineState();
        if ($currentOrderState === null) {
            throw WebhookException::orderWithoutState($transactionId,$orderNumber);
        }
        try {
            $this->logger->info('Start - Change order status', $logData);

            $newOrderStateId = $this->orderStateHandler->performTransition($shopwareOrder,$currentOrderState, $transition, $context);

            $logData['newOrderStateId'] = $newOrderStateId;
            $this->logger->info('Finished - Change order status', $logData);
        } catch (\Throwable $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Finished - Change order status, Failed to change order status', $logData);
            throw $exception;
        }
    }
}
