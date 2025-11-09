<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

trait HandlerTrait
{
    protected string $method;

    public function __construct(private Pay $pay,
        private Finalize $finalize,
        private TransactionConverterInterface $transactionConverter,
        private LoggerInterface $logger,
    ) {
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        return $payment;
    }

    public function getPaymentMethod(): string
    {
        return $this->method;
    }

    /**
     * @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction
     */
    public function doFinalize($transaction, Request $request, Context $context): void
    {
        try {
            $transaction = $this->transactionConverter->convert($transaction, $context);
            $this->finalize->execute($request, $transaction, $context);
        } catch (HttpException $exception) {
            // Catch Shopware Exceptions to show edit order page
            $this->logger->error('Payment is aborted or failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()
            ]);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('Payment failed unexpected', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()
            ]);
            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }

    /**
     * @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction
     */
    private function doPay($transaction, SalesChannelContext $salesChannelContext, RequestDataBag $dataBag): RedirectResponse
    {
        try {
            $transaction = $this->transactionConverter->convert($transaction, $salesChannelContext->getContext());

            return $this->pay->execute($this, $transaction, $dataBag, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->error('Mollie Pay Process Failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()
            ]);
            throw PaymentException::asyncProcessInterrupted($transaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }
}
