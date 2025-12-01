<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Transaction\TransactionConverter;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class MolliePaymentFacade
{
    public function __construct(
        #[Autowire(service: Pay::class)]
        private Pay $pay,
        #[Autowire(service: Finalize::class)]
        private Finalize $finalize,
        #[Autowire(service: TransactionConverter::class)]
        private TransactionConverterInterface $transactionConverter,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function pay(AbstractMolliePaymentHandler $paymentHandler, Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): RedirectResponse
    {
        $shopwareTransaction = $transaction;
        try {
            /** @var SalesChannelContext $salesChannelContext */
            $salesChannelContext = $request->get('sw-sales-channel-context');
            $dataBag = new RequestDataBag($request->request->all());

            $transaction = $this->transactionConverter->convert($shopwareTransaction, $salesChannelContext->getContext());

            return $this->pay->execute($paymentHandler, $transaction, $dataBag, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->error('Mollie Pay Process Failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $paymentHandler->getPaymentMethod()->value
            ]);
            throw PaymentException::asyncProcessInterrupted($shopwareTransaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }

    public function finalize(AbstractMolliePaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, Context $context): void
    {
        $shopwareTransaction = $transaction;
        try {
            $transaction = $this->transactionConverter->convert($shopwareTransaction, $context);
            $this->finalize->execute($transaction, $context);
        } catch (HttpException $exception) {
            $this->logger->error('Payment is aborted or failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $paymentHandler->getPaymentMethod()->value
            ]);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('Payment failed unexpected', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $paymentHandler->getPaymentMethod()->value
            ]);
            throw PaymentException::asyncFinalizeInterrupted($shopwareTransaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }
}
