<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilderInterface;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
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
    public function __construct(
        private CreatePaymentBuilderInterface $createPaymentBuilder,
        private MollieGatewayInterface $paymentGateway,
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        private OrderTransactionStateHandler $stateMachineHandler,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {
    }

    public function execute(AbstractMolliePaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $order = $transaction->getOrder();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelName = (string) $salesChannelContext->getSalesChannel()->getName();
        $transactionId = $transaction->getOrderTransactionId();
        $shopwareFinalizeUrl = $transaction->getReturnUrl();

        $context = $salesChannelContext->getContext();

        $this->logger->info('Start Mollie checkout', [
            'salesChannel' => $salesChannelName,
            'paymentMethod' => $paymentHandler->getPaymentMethod(),
            'orderNumber' => $orderNumber,
            'transactionId' => $transactionId,
        ]);

        $createPaymentStruct = $this->createPaymentStruct($transaction, $paymentHandler, $salesChannelName, $salesChannelContext);
        $countPayments = $this->updatePaymentCounter($transaction, $createPaymentStruct);

        $payment = $this->paymentGateway->createPayment($createPaymentStruct, $transaction->getOrder()->getSalesChannelId());

        $payment->setFinalizeUrl($shopwareFinalizeUrl);
        $payment->setCountPayments($countPayments);

        $this->orderTransactionRepository->savePaymentExtension($transaction->getOrderTransaction(), $payment, $context);

        $this->processPaymentStatus($paymentHandler, $transaction, $context);

        $redirectUrl = $payment->getCheckoutUrl();
        if (mb_strlen($redirectUrl) === 0) {
            $redirectUrl = $shopwareFinalizeUrl;
        }

        $this->logger->info('Mollie checkout finished, redirecting to payment provider', [
            'transactionId' => $transactionId,
            'redirectUrl' => $redirectUrl,
            'salesChannelName' => $salesChannelName,
            'orderNumber' => $orderNumber,
        ]);

        return new RedirectResponse($redirectUrl);
    }

    private function processPaymentStatus(AbstractMolliePaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, Context $context): void
    {
        $transactionId = $transaction->getOrderTransactionId();
        $orderNumber = (string) $transaction->getOrder()->getOrderNumber();
        try {
            $method = 'processUnconfirmed';
            if ($paymentHandler instanceof BankTransferAwareInterface) {
                $method = 'process';
            }

            $this->stateMachineHandler->{$method}($transactionId, $context);
            $this->logger->info('Changed payment status',[
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

    private function createPaymentStruct(PaymentTransactionStruct $transaction, AbstractMolliePaymentHandler $paymentHandler, string $salesChannelName, SalesChannelContext $salesChannelContext): CreatePayment
    {
        $order = $transaction->getOrder();
        $transactionId = $transaction->getOrderTransactionId();
        $orderNumber = (string) $order->getOrderNumber();
        $createPaymentStruct = $this->createPaymentBuilder->build($transaction->getOrderTransactionId(), $order);
        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethod());

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
