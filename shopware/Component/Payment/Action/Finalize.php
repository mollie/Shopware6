<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class Finalize
{
    public function __construct(
        private MollieGatewayInterface $mollieGateway,
        private AbstractSettingsService $settingsService,
        private OrderTransactionStateHandler $stateMachineHandler,
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
        $paymentStatus = $payment->getStatus();

        $environmentSettings = $this->settingsService->getEnvironmentSettings();

        if ($environmentSettings->isDevMode() || $environmentSettings->isCypressMode()) {
            $handlerMethod = $paymentStatus->getShopwareHandlerMethod();
            if (strlen($handlerMethod) > 0) {
                $this->stateMachineHandler->{$handlerMethod}($transaction->getOrderTransactionId(), $context);
            }
        }

        $this->logger->info('Fetched Payment Information from Mollie', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'oderNumber' => $transaction->getOrder()->getOrderNumber(),
            'paymentStatus' => $paymentStatus,
            'paymentId' => $payment->getId(),
        ]);

        if ($paymentStatus->isCancelled()) {
            $message = sprintf('Payment for order %s (%s) was cancelled by the customer.', $order->getOrderNumber(), $payment->getId());
            throw PaymentException::customerCanceled($transaction->getOrderTransactionId(), $message);
        }

        if ($paymentStatus->isFailed()) {
            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), 'Failed to finalize payment');
        }
    }
}
