<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\CancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\FailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class Finalize
{
    public function __construct(
        private MollieGatewayInterface $mollieGateway,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $this->logger->info('Start finalizing payment', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'oderNumber' => $transaction->getOrder()->getOrderNumber(),
        ]);

        $payment = $this->mollieGateway->getPaymentByTransactionId($transaction->getOrderTransactionId(), $context);
        $order = $payment->getShopwareTransaction()->getOrder();
        if (! $order instanceof OrderEntity) {
            throw new \Exception('Order not found'); // TODO: custom execption
        }
        $orderCustomer = $order->getOrderCustomer();
        if (! $orderCustomer instanceof OrderCustomerEntity) {
            throw new \Exception('Order customer not found');
        }
        $customer = $orderCustomer->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw new \Exception('Customer not found');
        }
        $paymentStatus = $payment->getStatus();

        $this->logger->info('Fetched Payment Information from Mollie', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'oderNumber' => $transaction->getOrder()->getOrderNumber(),
            'paymentStatus' => $paymentStatus,
            'paymentId' => $payment->getId(),
        ]);

        $finalizeEvent = new PaymentFinalizeEvent($payment, $context);
        $this->eventDispatcher->dispatch($finalizeEvent);

        if ($paymentStatus->isCancelled()) {
            $paymentCancelledEvent = new CancelledEvent($payment, $order, $customer, $context);
            $this->eventDispatcher->dispatch($paymentCancelledEvent);
            $message = sprintf('Payment for order %s (%s) was cancelled by the customer.', $order->getOrderNumber(), $payment->getId());
            throw PaymentException::customerCanceled($transaction->getOrderTransactionId(), $message);
        }

        if ($paymentStatus->isFailed()) {
            $paymentFailedEvent = new FailedEvent($payment, $order, $customer, $context);
            $this->eventDispatcher->dispatch($paymentFailedEvent);
            $message = sprintf('Payment for order %s (%s) is failed', $order->getOrderNumber(), $payment->getId());
            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), $message);
        }

        $paymentSuccessEvent = new SuccessEvent($payment, $order, $customer, $context);
        $this->eventDispatcher->dispatch($paymentSuccessEvent);
    }
}
