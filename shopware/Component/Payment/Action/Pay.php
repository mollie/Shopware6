<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\ModifyCreateOrderPayloadEvent;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\OrdersApiAwareInterface;
use Mollie\Shopware\Component\Payment\Method\PosPayment;
use Mollie\Shopware\Component\Payment\PayloadBuilder;
use Mollie\Shopware\Component\Payment\PayloadBuilderInterface;
use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class Pay implements PayInterface
{
    public function __construct(
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: PayloadBuilder::class)]
        private PayloadBuilderInterface $payloadBuilder,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: OrderTransactionStateHandler::class)]
        private OrderTransactionStateHandler $stateMachineHandler,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function execute(AbstractMolliePaymentHandler $paymentHandler,
        MollieTransactionStruct $transaction,
        RequestDataBag $dataBag,
        Context $context): RedirectResponse
    {
        $transactionId = $transaction->getOrderTransactionId();
        $shopwareFinalizeUrl = (string) $transaction->getReturnUrl();

        $transactionDataStruct = $this->transactionService->findById($transactionId, $context);

        $order = $transactionDataStruct->getOrder();
        $transaction = $transactionDataStruct->getTransaction();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannel = $transactionDataStruct->getSalesChannel();
        $salesChannelName = (string) $salesChannel->getName();

        $logData = [
            'salesChannel' => $salesChannelName,
            'paymentMethod' => $paymentHandler->getPaymentMethod()->value,
            'orderNumber' => $orderNumber,
            'transactionId' => $transactionId,
        ];

        $this->logger->info('Payment Process - Start', $logData);

        if ($paymentHandler instanceof OrdersApiAwareInterface) {
            return $this->executeOrdersApi($paymentHandler, $transactionDataStruct, $dataBag, $context, $order, $salesChannel, $transactionId, $orderNumber, $shopwareFinalizeUrl, $logData);
        }

        return $this->executePaymentsApi($paymentHandler, $transactionDataStruct, $transaction, $dataBag, $context, $salesChannel, $transactionId, $orderNumber, $shopwareFinalizeUrl, $logData);
    }

    /**
     * @param array<string, string> $logData
     */
    private function executeOrdersApi(
        AbstractMolliePaymentHandler $paymentHandler,
        TransactionDataStruct $transactionDataStruct,
        RequestDataBag $dataBag,
        Context $context,
        OrderEntity $order,
        SalesChannelEntity $salesChannel,
        string $transactionId,
        string $orderNumber,
        string $shopwareFinalizeUrl,
        array $logData
    ): RedirectResponse {
        $createOrderStruct = $this->payloadBuilder->buildOrder($transactionDataStruct, $paymentHandler, $dataBag, $context);

        $orderEvent = new ModifyCreateOrderPayloadEvent($createOrderStruct, $context);
        /** @var ModifyCreateOrderPayloadEvent $orderEvent */
        $orderEvent = $this->eventDispatcher->dispatch($orderEvent);
        $createOrderStruct = $orderEvent->getOrder();

        $mollieOrder = $this->mollieGateway->createOrder($createOrderStruct, $salesChannel->getId());

        $payment = new Payment($mollieOrder->getPaymentId());
        $payment->setOrderId($mollieOrder->getId());
        $payment->setFinalizeUrl($shopwareFinalizeUrl);

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context);

        $this->processPaymentStatus($paymentHandler, $transactionId, $orderNumber, $context);

        $redirectUrl = $mollieOrder->getCheckoutUrl();
        if (mb_strlen($redirectUrl) === 0) {
            $redirectUrl = $shopwareFinalizeUrl;
        }

        $paymentCreatedEvent = new PaymentCreatedEvent($redirectUrl, $payment, $transactionDataStruct, $dataBag, $context);
        $this->eventDispatcher->dispatch($paymentCreatedEvent);

        $logData['redirectUrl'] = $redirectUrl;
        $this->logger->info('Payment Process - Finished (Orders API), redirecting customer to URL', $logData);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param array<string, string> $logData
     */
    private function executePaymentsApi(
        AbstractMolliePaymentHandler $paymentHandler,
        TransactionDataStruct $transactionDataStruct,
        OrderTransactionEntity $transaction,
        RequestDataBag $dataBag,
        Context $context,
        SalesChannelEntity $salesChannel,
        string $transactionId,
        string $orderNumber,
        string $shopwareFinalizeUrl,
        array $logData
    ): RedirectResponse {
        $createPaymentStruct = $this->payloadBuilder->buildPayment($transactionDataStruct, $paymentHandler, $dataBag, $context);

        $countPayments = $this->updatePaymentCounter($transaction, $createPaymentStruct);

        /** @var RequestDataBag $paymentMethods */
        $paymentMethods = $dataBag->get('paymentMethods', new DataBag());

        if ($paymentMethods->count() > 0) {
            $createPaymentStruct->setMethods($paymentMethods->all());
        }
        $paymentEvent = new ModifyCreatePaymentPayloadEvent($createPaymentStruct, $context);
        /** @var ModifyCreatePaymentPayloadEvent $paymentEvent */
        $paymentEvent = $this->eventDispatcher->dispatch($paymentEvent);
        $createPaymentStruct = $paymentEvent->getPayment();
        $payment = $this->mollieGateway->createPayment($createPaymentStruct, $salesChannel->getId());

        $payment->setFinalizeUrl($shopwareFinalizeUrl);
        $payment->setCountPayments($countPayments);

        $this->transactionService->savePaymentExtension($transactionId, $transactionDataStruct->getOrder(), $payment, $context);

        $this->processPaymentStatus($paymentHandler, $transactionId, $orderNumber, $context);

        $redirectUrl = $payment->getCheckoutUrl();
        if ($paymentHandler instanceof PosPayment) {
            $redirectUrl = $this->routeBuilder->getPosCheckoutUrl($payment, $transactionId, $orderNumber);
        }
        if (mb_strlen($redirectUrl) === 0) {
            $redirectUrl = $shopwareFinalizeUrl;
        }

        $paymentCreatedEvent = new PaymentCreatedEvent($redirectUrl, $payment, $transactionDataStruct, $dataBag, $context);
        $this->eventDispatcher->dispatch($paymentCreatedEvent);

        $logData['redirectUrl'] = $redirectUrl;
        $this->logger->info('Payment Process - Finished, redirecting customer to URL', $logData);

        return new RedirectResponse($redirectUrl);
    }

    private function processPaymentStatus(AbstractMolliePaymentHandler $paymentHandler, string $transactionId, string $orderNumber, Context $context): void
    {
        $method = 'processUnconfirmed';
        $shopwareStatus = 'unconfirmed';
        if ($paymentHandler instanceof BankTransferAwareInterface) {
            $method = 'process';
            $shopwareStatus = 'in_progress';
        }
        $logData = [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'method' => $method,
            'shopwareStatus' => $shopwareStatus,
        ];

        try {
            $this->stateMachineHandler->{$method}($transactionId, $context);
            $this->logger->info('Changed payment status', $logData);
        } catch (IllegalTransitionException $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Failed to change payment status', $logData);
        }
    }

    private function updatePaymentCounter(OrderTransactionEntity $transaction, CreatePayment $createPaymentStruct): int
    {
        $countPayments = 1;
        $oldMollieTransaction = $transaction->getExtension(Mollie::EXTENSION);
        if ($oldMollieTransaction instanceof Payment) {
            $countPayments = $oldMollieTransaction->getCountPayments() + 1;
            $createPaymentStruct->setDescription($createPaymentStruct->getDescription() . '-' . $countPayments);
        }

        return $countPayments;
    }
}
