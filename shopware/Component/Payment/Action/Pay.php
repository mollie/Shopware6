<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
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
use Symfony\Component\HttpFoundation\RequestStack;

final class Pay implements PayInterface
{
    public const SESSION_KEY_PENDING_ORDER = 'mollie_pending_order_id';

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
        #[Autowire(service: 'request_stack')]
        private RequestStack $requestStack,
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

        // Bank transfer orders take several days to settle and must not be editable
        // afterwards, so we skip the pending-order session key that would redirect
        // the customer onto the edit-order form on browser back.
        if (! $paymentHandler instanceof BankTransferAwareInterface) {
            $this->requestStack->getSession()->set(self::SESSION_KEY_PENDING_ORDER, $order->getId());
            $this->logger->debug('[PendingOrderRedirect] session key set', ['orderId' => $order->getId()]);
        }

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

        $embeddedPayment = $mollieOrder->getPayment();
        $payment = new Payment($embeddedPayment->getId());
        $payment->setOrderId($mollieOrder->getId());
        $payment->setFinalizeUrl($shopwareFinalizeUrl);

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context, $mollieOrder);

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

        $countPayments = $this->updatePaymentCounter($transactionDataStruct->getOrder(), $createPaymentStruct);

        /** @var RequestDataBag $paymentMethods */
        $paymentMethods = $dataBag->get('paymentMethods', new DataBag());

        if ($paymentMethods->count() > 0) {
            $createPaymentStruct->setMethods($paymentMethods->all());
        }
        $paymentEvent = new ModifyCreatePaymentPayloadEvent($createPaymentStruct, $context);
        /** @var ModifyCreatePaymentPayloadEvent $paymentEvent */
        $paymentEvent = $this->eventDispatcher->dispatch($paymentEvent);
        $createPaymentStruct = $paymentEvent->getPayment();
        $payment = $this->createOrReusePayment($transaction, $createPaymentStruct, $orderNumber, $salesChannel->getId());

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

    /**
     * Shopware reuses the same transaction when the customer retries with the same payment method,
     * so the transaction may already hold a Mollie payment. To keep a single payment per transaction
     * we decide, based on the existing payment:
     * - none yet -> create a new payment (payments API);
     * - still open/pending and cancelable -> cancel it and create a fresh payment for the current cart;
     * - still open/pending but not cancelable -> reuse it and update the editable fields;
     * - already dead (failed/expired/cancelled) -> create a fresh payment.
     *
     * A changed cart total makes Shopware open a new transaction (no existing payment), so the reuse
     * paths always operate on an unchanged amount.
     */
    private function createOrReusePayment(OrderTransactionEntity $transaction, CreatePayment $createPaymentStruct, string $orderNumber, string $salesChannelId): Payment
    {
        $existing = $transaction->getExtension(Mollie::EXTENSION);
        if (! $existing instanceof Payment || $existing->getId() === '') {
            return $this->mollieGateway->createPayment($createPaymentStruct, $salesChannelId);
        }

        $existingId = $existing->getId();
        $live = $this->mollieGateway->getPayment($existingId, $orderNumber, $salesChannelId);

        if ($live->isCancelable()) {
            $this->mollieGateway->cancelPayment($existingId, $orderNumber, $salesChannelId);

            return $this->mollieGateway->createPayment($createPaymentStruct, $salesChannelId);
        }

        if (in_array($live->getStatus(), [PaymentStatus::OPEN, PaymentStatus::PENDING], true)) {
            return $this->mollieGateway->updatePayment($existingId, $createPaymentStruct, $orderNumber, $salesChannelId);
        }

        return $this->mollieGateway->createPayment($createPaymentStruct, $salesChannelId);
    }

    /**
     * The payment description carries the attempt number so several Mollie payments of the same
     * order are distinguishable. Deriving it from the number of order transactions is reliable even
     * though Shopware reuses one transaction per payment method (a stored per-transaction counter
     * would stay at 1 for every freshly created transaction).
     */
    private function updatePaymentCounter(OrderEntity $order, CreatePayment $createPaymentStruct): int
    {
        $transactions = $order->getTransactions();
        $countPayments = $transactions !== null ? $transactions->count() : 1;

        if ($countPayments > 1) {
            $createPaymentStruct->setDescription($createPaymentStruct->getDescription() . '-' . ($countPayments - 1));
        }

        return $countPayments;
    }
}
