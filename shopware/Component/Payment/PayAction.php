<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class PayAction
{
    private LoggerInterface $logger;
    private MolliePaymentDoPay $payFacade;
    private TransactionTransitionService $transactionTransitionService;

    /**
     * @param MolliePaymentDoPay $payFacade
     * @param TransactionTransitionService $transactionTransitionService
     * @param LoggerInterface $logger
     */
    public function __construct(MolliePaymentDoPay $payFacade, TransactionTransitionService $transactionTransitionService, LoggerInterface $logger)
    {
        $this->payFacade = $payFacade;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->logger = $logger;
    }

    /** @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction */
    public function pay(PaymentHandler $paymentHandler, $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {

        $this->logger->info(
            'Starting Checkout for order ' . $transaction->getOrder()->getOrderNumber() . ' with payment: ' . $paymentHandler->getPaymentMethod(),
            [
                'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                'cart' => [
                    'amount' => $transaction->getOrder()->getAmountTotal(),
                ],
            ]
        );

        try {
            $paymentData = $this->payFacade->startMolliePayment(
                $paymentHandler->getPaymentMethod(),
                $transaction,
                $salesChannelContext,
                $paymentHandler,
                $dataBag
            );

            $paymentUrl = $paymentData->getCheckoutURL();
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Error when starting Mollie payment: ' . $exception->getMessage(),
                [
                    'function' => 'order-prepare',
                    'exception' => $exception,
                ]
            );

            throw new PaymentUrlException($transaction->getOrderTransaction()->getId(), $exception->getMessage());
        }

        try {
            // before we send the customer to the Mollie payment page
            // we will process the order transaction, which means we set it to be IN PROGRESS.
            // this is just how it works at the moment, I did only add the comment for it here :)
            $this->transactionTransitionService->processTransaction($transaction->getOrderTransaction(), $salesChannelContext->getContext());
        } catch (\Exception $exception) {
            $this->logger->warning(
                sprintf('Could not set payment to in progress. Got error %s', $exception->getMessage())
            );
        }

        /*
         * Redirect the customer to the payment URL. Afterwards the
         * customer is redirected back to Shopware's finish page, which
         * leads to the @finalize function.
         */
        return new RedirectResponse($paymentUrl);
    }
}