<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilderInterface;
use Mollie\shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class Pay
{
    public function __construct(private CreatePaymentBuilderInterface $createPaymentBuilder,
        private MollieGatewayInterface $paymentGateway,
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        private OrderTransactionStateHandler $stateMachineHandler,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger)
    {
    }

    public function execute(CompatibilityPaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $salesChannelName = (string) $salesChannelContext->getSalesChannel()->getName();
        $this->logger->info('Start Mollie checkout', [
            'salesChannel' => $salesChannelName,
            'paymentMethod' => $paymentHandler->getPaymentMethod()
        ]);
        $shopwareFinalizeUrl = $transaction->getReturnUrl();

        $context = $salesChannelContext->getContext();

        $createPaymentStruct = $this->createPaymentStruct($transaction, $paymentHandler, $salesChannelName, $salesChannelContext);
        $countPayments = $this->updatePaymentCounter($transaction, $createPaymentStruct);

        $payment = $this->paymentGateway->createPayment($createPaymentStruct, $transaction->getOrder()->getSalesChannelId());

        $payment->setFinalizeUrl($shopwareFinalizeUrl);
        $payment->setCountPayments($countPayments);

        $this->logger->debug('Save payment information in Order Transaction', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'data' => $payment->toArray()
        ]);

        $this->orderTransactionRepository->savePaymentExtension($transaction->getOrderTransaction(), $payment, $context);

        $this->processPaymentStatus($paymentHandler, $transaction, $context);

        $redirectUrl = $payment->getCheckoutUrl() ?? $shopwareFinalizeUrl;
        $this->logger->info('Mollie checkout finished, redirecting to payment provider', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'redirectUrl' => $redirectUrl,
        ]);

        return new RedirectResponse($redirectUrl);
    }

    private function processPaymentStatus(CompatibilityPaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, Context $context): void
    {
        try {
            $method = 'processUnconfirmed';
            if ($paymentHandler instanceof BankTransferAwareInterface) {
                $method = 'process';
            }

            $this->stateMachineHandler->{$method}($transaction->getOrderTransactionId(), $context);
        } catch (IllegalTransitionException $exception) {
            $this->logger->error('Failed to change payment status', [
                'transactionId' => $transaction->getOrderTransactionId(),
                'reason' => $exception->getMessage()
            ]);
        }
    }

    private function createPaymentStruct(PaymentTransactionStruct $transaction, CompatibilityPaymentHandler $paymentHandler, string $salesChannelName, SalesChannelContext $salesChannelContext): CreatePayment
    {
        $order = $transaction->getOrder();
        $createPaymentStruct = $this->createPaymentBuilder->build($transaction->getOrderTransactionId(), $order);
        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethod());

        $createPaymentStruct = $paymentHandler->applyPaymentSpecificParameters($createPaymentStruct, $order);
        $this->logger->info('Payment payload created, send data to Mollie API', [
            'payload' => $createPaymentStruct->toArray(),
            'salesChannel' => $salesChannelName,
        ]);

        $paymentEvent = new ModifyCreatePaymentPayloadEvent($createPaymentStruct, $salesChannelContext);
        $this->eventDispatcher->dispatch($paymentEvent);

        return $paymentEvent->getPayment();
    }

    private function updatePaymentCounter(PaymentTransactionStruct $transaction, CreatePayment $createPaymentStruct): int
    {
        $countPayments = 1;
        $oldMollieTransaction = $transaction->getOrderTransaction()->getExtension(Mollie::EXTENSION);
        if ($oldMollieTransaction instanceof Payment) {
            $countPayments = $oldMollieTransaction->getCountPayments() + 1;
            $createPaymentStruct->setDescription($createPaymentStruct->getDescription() . '-' . $countPayments);
        }

        return $countPayments;
    }
}
