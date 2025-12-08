<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Exception\TransactionWithoutOrderException;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\PaymentMethodRepository;
use Mollie\Shopware\Repository\PaymentMethodRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

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
            // TODO: use custom execption
            throw new \Exception('Shopware order not found for TransactionId: ' . $transactionId);
        }
        $orderNumber = (string) $shopwareOrder->getOrderNumber();
        $webhookEvent = new WebhookEvent($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookEvent);
        $webhookStatusEventClass = $payment->getStatus()->getWebhookEventClass();
        $webhookStatusEvent = new $webhookStatusEventClass($payment, $shopwareOrder, $context);
        $this->eventDispatcher->dispatch($webhookStatusEvent);

        $this->updatePaymentStatus($payment, $transactionId, $orderNumber, $context);
        $this->updatePaymentMethod($payment, $orderNumber, $shopwareOrder->getSalesChannelId(), $context);

        // TODO: update order status
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
        }
    }

    private function updatePaymentMethod(Payment $payment, string $orderNumber, string $salesChannelId, Context $context): void
    {
        $transaction = $payment->getShopwareTransaction();
        $transactionId = $transaction->getId();
        $paymentMethod = $transaction->getPaymentMethod();
        $molliePaymentMethod = $payment->getMethod()->value;

        $logData = [
            'transactionId' => $transactionId,
            'molliePaymentMethod' => $molliePaymentMethod,
            'orderNumber' => $orderNumber,
        ];

        $this->logger->info('Change payment method if changed', $logData);

        if ($paymentMethod === null) {
            throw new TransactionWithoutOrderException($transactionId);
        }
        /** @var ?PaymentMethodExtension $molliePaymentMethodExtension */
        $molliePaymentMethodExtension = $paymentMethod->getExtension(Mollie::EXTENSION);

        if ($molliePaymentMethodExtension === null) {
            throw new \Exception('Mollie payment method not found for TransactionId: ' . $transactionId);
        }
        $logData['shopwarePaymentMethod'] = $molliePaymentMethodExtension->getPaymentMethod()->value;

        $this->logger->debug('Start to compare payment', $logData);

        if ($molliePaymentMethodExtension->getPaymentMethod() === $payment->getMethod()) {
            $this->logger->debug('Payment methods are the same', $logData);

            return;
        }

        if ($molliePaymentMethodExtension->getPaymentMethod() === PaymentMethod::APPLEPAY && $payment->getMethod() === PaymentMethod::CREDIT_CARD) {
            $this->logger->debug('Apple Pay payment methods are stored as credit card in mollie, no change needed', $logData);

            return;
        }

        $this->logger->debug('Payment methods are different, try to find payment method based on mollies payment method name', $logData);

        $newPaymentMethodId = $this->paymentMethodRepository->getIdForPaymentMethod($payment->getMethod(), $salesChannelId, $context);

        if ($newPaymentMethodId === null) {
            throw new \Exception('Payment method not found for TransactionId: ' . $transactionId);
        }

        $this->orderTransactionRepository->upsert([
            [
                'id' => $transactionId,
                'paymentMethodId' => $newPaymentMethodId
            ]
        ], $context);

        $this->logger->info('Changed payment methods for transaction', $logData);
    }
}
