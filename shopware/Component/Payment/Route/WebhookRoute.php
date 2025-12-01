<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Exception\TransactionWithoutOrderException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class WebhookRoute extends AbstractWebhookRoute
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        private OrderTransactionStateHandler $stateMachineHandler,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        private PaymentMethodRepository $paymentMethodRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function notify(Request $request, Context $context): WebhookRouteResponse
    {
        $transactionId = $request->get('transactionId');

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
        $this->updatePaymentMethod($payment,$orderNumber);

        // TODO: update order status
        return new WebhookRouteResponse();
    }

    private function updatePaymentStatus(Payment $payment, string $transactionId, string $shopwareOrder, Context $context): void
    {
        $shopwareHandlerMethod = $payment->getStatus()->getShopwareHandlerMethod();
        $logData = [
            'transactionId' => $transactionId,
            'paymentStatus' => $payment->getStatus()->value,
            'shopwareMethod' => $shopwareHandlerMethod,
            'shopwareOrder' => $shopwareOrder,
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

    private function updatePaymentMethod(Payment $payment,string $shopwareOrder): void
    {
        $transaction = $payment->getShopwareTransaction();
        $transactionId = $transaction->getId();
        $paymentMethod = $transaction->getPaymentMethod();

        $logData = [
            'transactionId' => $transactionId,
            'molliePaymentMethod' => $payment->getMethod()->value,
            'shopwareOrder' => $shopwareOrder,
        ];

        $this->logger->info('Change payment method if changed', $logData);

        if ($paymentMethod === null) {
            throw new TransactionWithoutOrderException($transactionId);
        }

        $paymentHandlerIdentifier = $paymentMethod->getHandlerIdentifier();
        /** @var AbstractMolliePaymentHandler $paymentHandler */
        $paymentHandler = $this->paymentMethodRepository->findByIdentifier($paymentHandlerIdentifier);

        $this->logger->debug('Start to compare payment');

        if ($paymentHandler->getPaymentMethod() === $payment->getMethod()) {
            return;
        }
        /** Apple Pay payment is stored as credit card on mollie side, so we do not want to switch payment method */
        if ($paymentHandler->getPaymentMethod() === PaymentMethod::APPLEPAY && $payment->getMethod() === PaymentMethod::CREDIT_CARD) {
            return;
        }
        // TODO: update payment method
    }
}
