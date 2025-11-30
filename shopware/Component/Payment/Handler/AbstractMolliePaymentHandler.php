<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractMolliePaymentHandler extends AbstractPaymentHandler
{
    protected string $method;

    public function __construct(private Pay $pay,
        private Finalize $finalize,
        private TransactionConverterInterface $transactionConverter,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $request->get('sw-sales-channel-context');
        $dataBag = new RequestDataBag($request->request->all());
        $shopwareTransaction = $transaction;

        try {
            $transaction = $this->transactionConverter->convert($shopwareTransaction, $salesChannelContext->getContext());

            return $this->pay->execute($this, $transaction, $dataBag, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->error('Mollie Pay Process Failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()
            ]);
            throw PaymentException::asyncProcessInterrupted($shopwareTransaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $shopwareTransaction = $transaction;
        try {
            $transaction = $this->transactionConverter->convert($shopwareTransaction, $context);
            $this->finalize->execute($transaction, $context);
        } catch (HttpException $exception) {
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
            throw PaymentException::asyncFinalizeInterrupted($shopwareTransaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        return $payment;
    }

    public function getPaymentMethod(): string
    {
        return $this->method;
    }
}
