<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;

final class FinalizeAction
{
    private LoggerInterface $logger;
    private MolliePaymentFinalize $finalizeFacade;
    private TransactionConverterInterface $transactionConverter;

    public function __construct(MolliePaymentFinalize $finalizeFacade, TransactionConverterInterface $transactionConverter, LoggerInterface $logger)
    {
        $this->finalizeFacade = $finalizeFacade;
        $this->logger = $logger;
        $this->transactionConverter = $transactionConverter;
    }

    /** @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction */
    public function finalize(PaymentHandler $paymentHandler, $transaction, Context $context): void
    {
        try {
            $transaction = $this->transactionConverter->convert($transaction, $context);

            $shopwareOrder = $transaction->getOrder();
            $salesChannelId = $shopwareOrder->getSalesChannelId();
            $orderAttributes = new OrderAttributes($shopwareOrder);
            $mollieID = $orderAttributes->getMollieOrderId();

            $this->logger->info(
                'Finalizing Mollie payment for order ' . $transaction->getOrder()->getOrderNumber() . ' with payment: ' . $paymentHandler->getPaymentMethod() . ' and Mollie ID' . $mollieID,
                [
                    'salesChannelId' => $salesChannelId, //todo: add a name somehow
                ]
            );

            $this->finalizeFacade->finalize($transaction, $context, (string) $salesChannelId);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException|PaymentException $ex) {
            $this->logger->error(
                'Error when finalizing order ' . $transaction->getOrder()->getOrderNumber() . ', Mollie ID: ' . $mollieID . ', ' . $ex->getMessage()
            );

            // these are already correct exceptions
            // that cancel the Shopware order in a coordinated way by Shopware
            throw $ex;
        } catch (\Throwable $ex) {
            // this processes all unhandled exceptions.
            // we need to log whatever happens in here, and then also
            // throw an exception that breaks the order in a coordinated way.
            // Only the 2 exceptions above, lead to a correct failure-behaviour in Shopware.
            // All other exceptions would lead to a 500 exception in the storefront.
            $this->logger->error(
                'Unknown Error when finalizing order ' . $transaction->getOrder()->getOrderNumber() . ', Mollie ID: ' . $mollieID . ', ' . $ex->getMessage()
            );
            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), 'An unknown error happened when finalizing the order. Please see the Shopware logs for more. It can be that the payment in Mollie was succesful and the Shopware order is now cancelled or failed!');
        }
    }
}
