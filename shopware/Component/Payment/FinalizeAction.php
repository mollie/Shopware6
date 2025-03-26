<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FinalizeAction
{
    private LoggerInterface $logger;
    private MolliePaymentFinalize $finalizeFacade;

    /**
     * @param MolliePaymentFinalize $finalizeFacade
     * @param LoggerInterface $logger
     */
    public function __construct(MolliePaymentFinalize $finalizeFacade, LoggerInterface $logger)
    {
        $this->finalizeFacade = $finalizeFacade;
        $this->logger = $logger;
    }

    /** @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction */
    public function finalize(PaymentHandler $paymentHandler, $transaction, SalesChannelContext $salesChannelContext): void
    {

        $orderAttributes = new OrderAttributes($transaction->getOrder());
        $mollieID = $orderAttributes->getMollieOrderId();

        $this->logger->info(
            'Finalizing Mollie payment for order ' . $transaction->getOrder()->getOrderNumber() . ' with payment: ' . $paymentHandler->getPaymentMethod() . ' and Mollie ID' . $mollieID,
            [
                'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
            ]
        );

        try {
            $this->finalizeFacade->finalize($transaction, $salesChannelContext);
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
            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransaction()->getId(), 'An unknown error happened when finalizing the order. Please see the Shopware logs for more. It can be that the payment in Mollie was succesful and the Shopware order is now cancelled or failed!');
        }
    }
}