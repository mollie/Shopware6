<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\CancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\FailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Finalize
{
    public function __construct(
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionDataLoader,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function execute(PaymentTransactionStruct $transaction, Context $context): void
    {
        $transactionId = $transaction->getOrderTransactionId();
        $transactionData = $this->transactionDataLoader->findById($transactionId,$context);

        $order = $transactionData->getOrder();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        $customer = $transactionData->getCustomer();

        $logData = [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->info('Finalize Process - Start', $logData);
        // We need to change that part at some point. Because inside getPaymentByTraansactionId also calls the transactionData Loader.
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $paymentStatus = $payment->getStatus();

        $logData['paymentId'] = $payment->getId();
        $logData['paymentStatus'] = $paymentStatus->value;

        $this->logger->info('Fetched Payment Information from Mollie', $logData);

        $finalizeEvent = new PaymentFinalizeEvent($payment, $context);
        $this->eventDispatcher->dispatch($finalizeEvent);

        $this->logger->debug('PaymentFinalizeEvent fired', $logData);

        if ($paymentStatus->isCanceled()) {
            $message = sprintf('Payment for order %s (%s) was cancelled by the customer.', $orderNumber, $payment->getId());
            $this->logger->warning('Finalize Process - Finished. Payment was cancelled, CancelledEvent fired', $logData);

            $paymentCancelledEvent = new CancelledEvent($payment, $order, $customer, $context);
            $this->eventDispatcher->dispatch($paymentCancelledEvent);

            throw PaymentException::customerCanceled($transaction->getOrderTransactionId(), $message);
        }

        if ($paymentStatus->isFailed()) {
            $message = sprintf('Payment for order %s (%s) is failed', $orderNumber, $payment->getId());

            $this->logger->warning('Finalize Process - Finished. Payment is failed, FailedEvent fired', $logData);

            $paymentFailedEvent = new FailedEvent($payment, $order, $customer, $context);
            $this->eventDispatcher->dispatch($paymentFailedEvent);

            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), $message);
        }
        $this->logger->info('Finalize Process - Finished. Payment is successful, SuccessEvent fired', $logData);
        $paymentSuccessEvent = new SuccessEvent($payment, $order, $customer, $context);
        $this->eventDispatcher->dispatch($paymentSuccessEvent);
    }
}
