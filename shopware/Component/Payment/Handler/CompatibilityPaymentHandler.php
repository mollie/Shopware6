<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

if (class_exists(AbstractPaymentHandler::class)) {
    abstract class CompatibilityPaymentHandler extends AbstractPaymentHandler
    {
        use HandlerTrait;

        public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
        {
            return false;
        }

        public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
        {
            /** @var SalesChannelContext $salesChannelContext */
            $salesChannelContext = $request->get('sw-sales-channel-context');
            $transaction = $this->transactionConverter->convert($transaction, $context);

            return $this->pay->execute($this, $transaction, new RequestDataBag($request->request->all()), $salesChannelContext);
        }

        public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
        {
            $transaction = $this->transactionConverter->convert($transaction, $context);
            $this->finalize->execute($request, $transaction, $context);
        }
    }

    return;
}

/** @phpstan-ignore-next-line  */
if (interface_exists(AsynchronousPaymentHandlerInterface::class) && ! class_exists(AbstractPaymentHandler::class)) {
    abstract class CompatibilityPaymentHandler implements AsynchronousPaymentHandlerInterface
    {
        use HandlerTrait;

        public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
        {
            $transaction = $this->transactionConverter->convert($transaction, $salesChannelContext->getContext());

            return $this->pay->execute($this, $transaction, $dataBag, $salesChannelContext);
        }

        public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
        {
            $transaction = $this->transactionConverter->convert($transaction, $salesChannelContext->getContext());
            $this->finalize->execute($request, $transaction, $salesChannelContext->getContext());
        }
    }
}
