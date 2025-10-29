<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CreatePaymentBuilderInterface;
use Mollie\shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Payment\Event\CreatePaymentEvent;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use Mollie\Shopware\Entity\OrderTransaction\OrderTransaction;
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
            'paymentMethod' => $paymentHandler->getPaymentMethodName()
        ]);
        $context = $salesChannelContext->getContext();

        $oldMollieTransaction = $transaction->getOrderTransaction()->getExtension(Mollie::EXTENSION);
        $contPayments = 1;

        $shopwareFinalizeUrl = $transaction->getReturnUrl();

        $createPaymentStruct = $this->createPaymentBuilder->build($transaction);
        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethodName());

        if ($oldMollieTransaction instanceof OrderTransaction) {
            $contPayments = $oldMollieTransaction->getCountPayments() + 1;
            $createPaymentStruct->setDescription($createPaymentStruct->getDescription() . '-' . $contPayments);
        }
        $this->logger->info('Payment payload created, send data to Mollie API', [
            'payload' => $createPaymentStruct->toArray(),
            'salesChannel' => $salesChannelName,
        ]);

        $paymentEvent = new CreatePaymentEvent($createPaymentStruct, $salesChannelContext);
        $this->eventDispatcher->dispatch($paymentEvent);

        $paymentResult = $this->paymentGateway->createPayment($paymentEvent->getPayment(), $transaction->getOrder()->getSalesChannelId());

        $this->logger->info('Payment created', [
            'paymentId' => $paymentResult->getPaymentId(),
            'checkoutUrl' => $paymentResult->getCheckoutUrl(),
        ]);

        $redirectUrl = $paymentResult->getCheckoutUrl() ?? $shopwareFinalizeUrl;

        $mollieTransactionData = new OrderTransaction($paymentResult->getPaymentId(), $shopwareFinalizeUrl, $contPayments);
        $this->logger->debug('Save payment information in Order Transaction', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'data' => $mollieTransactionData->all()
        ]);

        $this->orderTransactionRepository->saveTransactionData($transaction->getOrderTransaction(), $mollieTransactionData, $context);

        $this->processPaymentStatus($paymentHandler, $transaction, $context);

        $this->logger->info('Mollie checkout finished, redirecting to payment provider', [
            'transactionId' => $transaction->getOrderTransactionId(),
            'redirectUrl' => $redirectUrl,
        ]);

        return new RedirectResponse($redirectUrl);
    }

    public function processPaymentStatus(CompatibilityPaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, Context $context): void
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
}
