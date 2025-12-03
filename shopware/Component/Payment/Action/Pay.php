<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilder;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilderInterface;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class Pay
{
    public function __construct(
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: CreatePaymentBuilder::class)]
        private CreatePaymentBuilderInterface $createPaymentBuilder,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $paymentGateway,
        #[Autowire(service: OrderTransactionStateHandler::class)]
        private OrderTransactionStateHandler $stateMachineHandler,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function execute(AbstractMolliePaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $transactionId = $transaction->getOrderTransactionId();
        $shopwareFinalizeUrl = (string) $transaction->getReturnUrl();
        $context = $salesChannelContext->getContext();

        $transactionDataStruct = $this->transactionService->findById($transactionId, $context);

        $order = $transactionDataStruct->getOrder();
        $transaction = $transactionDataStruct->getTransaction();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $salesChannelName = (string) $salesChannel->getName();

        $logData = [
            'salesChannel' => $salesChannelName,
            'paymentMethod' => $paymentHandler->getPaymentMethod()->value,
            'orderNumber' => $orderNumber,
            'transactionId' => $transactionId,
        ];

        $this->logger->info('Start - Mollie payment', $logData);

        $createPaymentStruct = $this->createPaymentStruct($transactionDataStruct, $paymentHandler, $salesChannelName, $salesChannelContext);
        $countPayments = $this->updatePaymentCounter($transaction, $createPaymentStruct);

        $payment = $this->paymentGateway->createPayment($createPaymentStruct, $salesChannel->getId());

        $payment->setFinalizeUrl($shopwareFinalizeUrl);
        $payment->setCountPayments($countPayments);

        $this->transactionService->savePaymentExtension($transactionId,$order, $payment, $context);

        $this->processPaymentStatus($paymentHandler, $transactionId, $orderNumber, $context);

        $redirectUrl = $payment->getCheckoutUrl();
        if (mb_strlen($redirectUrl) === 0) {
            $redirectUrl = $shopwareFinalizeUrl;
        }
        $logData['redirectUrl'] = $redirectUrl;
        $this->logger->info('Finished - Mollie payment, redirecting', $logData);

        return new RedirectResponse($redirectUrl);
    }

    private function processPaymentStatus(AbstractMolliePaymentHandler $paymentHandler, string $transactionId, string $orderNumber, Context $context): void
    {
        try {
            $method = 'processUnconfirmed';
            if ($paymentHandler instanceof BankTransferAwareInterface) {
                $method = 'process';
            }

            $this->stateMachineHandler->{$method}($transactionId, $context);
            $this->logger->info('Changed payment status', [
                'transactionId' => $transactionId,
                'orderNumber' => $orderNumber,
                'method' => $method,
            ]);
        } catch (IllegalTransitionException $exception) {
            $this->logger->error('Failed to change payment status', [
                'transactionId' => $transactionId,
                'reason' => $exception->getMessage(),
                'orderNumber' => $orderNumber,
            ]);
        }
    }

    private function createPaymentStruct(TransactionDataStruct $transaction, AbstractMolliePaymentHandler $paymentHandler, string $salesChannelName, SalesChannelContext $salesChannelContext): CreatePayment
    {
        $order = $transaction->getOrder();
        $transactionId = $transaction->getTransaction()->getId();
        $orderNumber = (string) $order->getOrderNumber();

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelContext->getSalesChannelId());

        $createPaymentStruct = $this->createPaymentBuilder->build($transaction);
        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethod());

        if ($paymentHandler instanceof ManualCaptureModeAwareInterface) {
            $createPaymentStruct->setCaptureMode(CaptureMode::MANUAL);
        }

        if ($paymentHandler instanceof BankTransferAwareInterface && $paymentSettings->getDueDateDays() > 0) {
            $dueDate = new \DateTime('now', new \DateTimeZone('UTC'));
            $dueDate->modify('+' . $paymentSettings->getDueDateDays() . ' days');
            $createPaymentStruct->setDueDate($dueDate);
        }

        $createPaymentStruct = $paymentHandler->applyPaymentSpecificParameters($createPaymentStruct, $order);
        $this->logger->info('Payment payload created for mollie API', [
            'payload' => $createPaymentStruct->toArray(),
            'salesChannel' => $salesChannelName,
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
        ]);

        $paymentEvent = new ModifyCreatePaymentPayloadEvent($createPaymentStruct, $salesChannelContext);
        $this->eventDispatcher->dispatch($paymentEvent);

        return $paymentEvent->getPayment();
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
