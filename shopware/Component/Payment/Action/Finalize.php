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
            'oderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->info('Returned from payment page back to shop, start finalizing payment', $logData);

        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        $paymentStatus = $payment->getStatus();

        $logData['paymentId'] = $payment->getId();
        $logData['paymentStatus'] = $paymentStatus;

        $this->logger->info('Fetched Payment Information from Mollie', $logData);

        $finalizeEvent = new PaymentFinalizeEvent($payment, $context);
        $this->eventDispatcher->dispatch($finalizeEvent);

        $this->logger->debug('PaymentFinalizeEvent fired', $logData);

        if ($paymentStatus->isCanceled()) {
            $paymentCancelledEvent = new CancelledEvent($payment, $order, $customer, $context);
            $this->eventDispatcher->dispatch($paymentCancelledEvent);
            $message = sprintf('Payment for order %s (%s) was cancelled by the customer.', $orderNumber, $payment->getId());
            $this->logger->warning('Finalize finished, payment was cancelled, CancelledEvent fired', $logData);
            throw PaymentException::customerCanceled($transaction->getOrderTransactionId(), $message);
        }

        if ($paymentStatus->isFailed()) {
            $paymentFailedEvent = new FailedEvent($payment, $order, $customer, $context);
            $this->eventDispatcher->dispatch($paymentFailedEvent);
            $message = sprintf('Payment for order %s (%s) is failed', $orderNumber, $payment->getId());

            $this->logger->warning('Finalize finished, payment is failed, FailedEvent fired', $logData);

            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), $message);
        }

        $paymentSuccessEvent = new SuccessEvent($payment, $order, $customer, $context);
        $this->eventDispatcher->dispatch($paymentSuccessEvent);

        $this->logger->info('Finalize finished, Payment is successful, SuccessEvent fired', $logData);
    }
}
