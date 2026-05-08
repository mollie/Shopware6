<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

if (Feature::isActive('v6.7.0.0') || ! interface_exists(AsynchronousPaymentHandlerInterface::class)) {
    #[AutoconfigureTag('shopware.payment.method')]
    #[AutoconfigureTag('shopware.payment.method.async')]
    #[AutoconfigureTag('mollie.payment.method')]
    abstract class AbstractMolliePaymentHandler extends AbstractPaymentHandler
    {
        use MolliePaymentHandlerTrait;

        public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): RedirectResponse
        {
            $struct = new MollieTransactionStruct(
                $transaction->getOrderTransactionId(),
                (string) $transaction->getReturnUrl()
            );

            try {
                $dataBag = new RequestDataBag($request->request->all());

                return $this->pay->execute($this, $struct, $dataBag, $context);
            } catch (\Throwable $exception) {
                $this->logger->error('Mollie Pay Process Failed', [
                    'error' => $exception->getMessage(),
                    'paymentMethod' => $this->getPaymentMethod()->value,
                ]);

                throw PaymentException::asyncProcessInterrupted($struct->getOrderTransactionId(), $exception->getMessage(), $exception);
            }
        }

        public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
        {
            $struct = new MollieTransactionStruct(
                $transaction->getOrderTransactionId(),
                (string) $transaction->getReturnUrl()
            );

            try {
                $this->finalize->execute($struct, $context);
            } catch (HttpException $exception) {
                $this->logger->error('Payment is aborted or failed', [
                    'error' => $exception->getMessage(),
                    'paymentMethod' => $this->getPaymentMethod()->value,
                ]);

                throw $exception;
            } catch (\Throwable $exception) {
                $this->logger->error('Payment failed unexpected', [
                    'error' => $exception->getMessage(),
                    'paymentMethod' => $this->getPaymentMethod()->value,
                    'trace' => $exception->getTrace(),
                ]);

                throw PaymentException::asyncFinalizeInterrupted($struct->getOrderTransactionId(), $exception->getMessage(), $exception);
            }
        }

        public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
        {
            return false;
        }
    }
} else {
    #[AutoconfigureTag('shopware.payment.method')]
    #[AutoconfigureTag('shopware.payment.method.async')]
    #[AutoconfigureTag('mollie.payment.method')]
    abstract class AbstractMolliePaymentHandler implements AsynchronousPaymentHandlerInterface
    {
        use MolliePaymentHandlerTrait;

        public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
        {
            $struct = new MollieTransactionStruct(
                $transaction->getOrderTransaction()->getId(),
                $transaction->getReturnUrl()
            );

            try {
                return $this->pay->execute($this, $struct, $dataBag, $salesChannelContext->getContext());
            } catch (\Throwable $exception) {
                $this->logger->error('Mollie Pay Process Failed', [
                    'error' => $exception->getMessage(),
                    'paymentMethod' => $this->getPaymentMethod()->value,
                ]);

                throw PaymentException::asyncProcessInterrupted($struct->getOrderTransactionId(), $exception->getMessage(), $exception);
            }
        }

        public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
        {
            $struct = new MollieTransactionStruct(
                $transaction->getOrderTransaction()->getId(),
                $transaction->getReturnUrl()
            );

            try {
                $this->finalize->execute($struct, $salesChannelContext->getContext());
            } catch (HttpException $exception) {
                $this->logger->error('Payment is aborted or failed', [
                    'error' => $exception->getMessage(),
                    'paymentMethod' => $this->getPaymentMethod()->value,
                ]);

                throw $exception;
            } catch (\Throwable $exception) {
                $this->logger->error('Payment failed unexpected', [
                    'error' => $exception->getMessage(),
                    'paymentMethod' => $this->getPaymentMethod()->value,
                    'trace' => $exception->getTrace(),
                ]);

                throw PaymentException::asyncFinalizeInterrupted($struct->getOrderTransactionId(), $exception->getMessage(), $exception);
            }
        }
    }
}
