<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\CreatePaymentBuilder;
use Mollie\Shopware\Component\Payment\CreatePaymentBuilderInterface;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Method\PosPayment;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class Pay
{
    public function __construct(
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface   $transactionService,
        #[Autowire(service: CreatePaymentBuilder::class)]
        private CreatePaymentBuilderInterface $createPaymentBuilder,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface        $mollieGateway,
        #[Autowire(service: OrderTransactionStateHandler::class)]
        private OrderTransactionStateHandler  $stateMachineHandler,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface         $routeBuilder,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface      $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface               $logger
    )
    {
    }

    public function execute(AbstractMolliePaymentHandler $paymentHandler,
                            PaymentTransactionStruct     $transaction,
                            RequestDataBag               $dataBag,
                            Context                      $context): RedirectResponse
    {
        $transactionId = $transaction->getOrderTransactionId();
        $shopwareFinalizeUrl = (string)$transaction->getReturnUrl();

        $transactionDataStruct = $this->transactionService->findById($transactionId, $context);

        $order = $transactionDataStruct->getOrder();
        $transaction = $transactionDataStruct->getTransaction();
        $orderNumber = (string)$order->getOrderNumber();
        $salesChannel = $transactionDataStruct->getSalesChannel();
        $salesChannelName = (string)$salesChannel->getName();

        $logData = [
            'salesChannel' => $salesChannelName,
            'paymentMethod' => $paymentHandler->getPaymentMethod()->value,
            'orderNumber' => $orderNumber,
            'transactionId' => $transactionId,
        ];

        $this->logger->info('Start - Mollie payment', $logData);

        $createPaymentStruct = $this->createPaymentBuilder->build($transactionDataStruct, $paymentHandler, $dataBag, $context);

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
        $paypalExpressAuthenticationId = $createPaymentStruct->getAuthenticationId();
        if ($paypalExpressAuthenticationId !== null) {
            $payment->setAuthenticationId($paypalExpressAuthenticationId);
        }

        $payment->setFinalizeUrl($shopwareFinalizeUrl);
        $payment->setCountPayments($countPayments);

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context);

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
        $this->logger->info('Finished - Mollie payment, redirecting', $logData);

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
