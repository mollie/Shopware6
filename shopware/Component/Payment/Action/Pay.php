<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;
use Mollie\Shopware\Component\Payment\Mollie\RequestFactoryInterface;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

final class Pay
{
    private TransactionConverterInterface $transactionConverter;
    private LoggerInterface $logger;
    private RequestFactoryInterface $requestFactory;

    public function __construct(RequestFactoryInterface $requestFactory,TransactionConverterInterface $transactionConverter, LoggerInterface $logger)
    {
        $this->requestFactory = $requestFactory;
        $this->transactionConverter = $transactionConverter;
        $this->logger = $logger;
    }

    /** @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction */
    public function execute(CompatibilityPaymentHandler $paymentHandler, $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $transaction = $this->transactionConverter->convert($transaction, $salesChannelContext->getContext());

        $order = $transaction->getOrder();
        $request = $this->requestFactory->createPayment($order);

        throw new \Exception('test');
    }
}