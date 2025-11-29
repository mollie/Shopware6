<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

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
        $dataBag = new RequestDataBag($request->request->all());

        return $this->doPay($transaction, $salesChannelContext, $dataBag);
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $this->doFinalize($transaction, $request, $context);
    }
}
