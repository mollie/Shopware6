<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Shopware\Component\Transaction\TransactionConverter;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FinalizeAction
{
    public function __construct(
        private MolliePaymentFinalize $finalizeFacade,
        #[Autowire(service: TransactionConverter::class)]
        private TransactionConverterInterface $transactionConverter,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger)
    {
    }

    public function finalize(PaymentHandler $paymentHandler,PaymentTransactionStruct $shopwareTransaction, Context $context): void
    {
        $mollieID = 'notLoaded';
        $shopOrderNumber = 'notLoaded';
        try {
            $transaction = $this->transactionConverter->convert($shopwareTransaction, $context);

            $shopwareOrder = $transaction->getOrder();
            $salesChannelId = $shopwareOrder->getSalesChannelId();
            $orderAttributes = new OrderAttributes($shopwareOrder);
            $mollieID = $orderAttributes->getMollieOrderId();
            $shopOrderNumber = $shopwareOrder->getOrderNumber();
            $this->logger->info(
                'Finalizing Mollie payment for order ' . $shopOrderNumber . ' with payment: ' . $paymentHandler->getPaymentMethod() . ' and Mollie ID' . $mollieID,
                [
                    'salesChannelId' => $salesChannelId, // todo: add a name somehow
                ]
            );

            $this->finalizeFacade->finalize($transaction, $context, (string) $salesChannelId);
        } catch (PaymentException $ex) {
            $this->logger->error(
                'Error when finalizing order ' . $shopOrderNumber . ', Mollie ID: ' . $mollieID . ', ' . $ex->getMessage()
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
                'Unknown Error when finalizing order ' . $shopOrderNumber . ', Mollie ID: ' . $mollieID . ', ' . $ex->getMessage()
            );
            throw PaymentException::asyncFinalizeInterrupted($shopwareTransaction->getOrderTransactionId(), 'An unknown error happened when finalizing the order. Please see the Shopware logs for more. It can be that the payment in Mollie was succesful and the Shopware order is now cancelled or failed!');
        }
    }
}
