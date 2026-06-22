<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Legacy base payment handler. The Mollie payment flow runs through
 * Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler and its
 * method handlers. This class only survives because its subclasses are referenced
 * by their ::class string as historical handlerIdentifiers (e.g. ApplePayPayment).
 * It is never registered as a payment handler, so pay()/finalize() are never invoked.
 */
class PaymentHandler extends AbstractPaymentHandler
{
    public const PAYMENT_SEQUENCE_TYPE_FIRST = 'first';
    public const PAYMENT_SEQUENCE_TYPE_RECURRING = 'recurring';

    protected string $paymentMethod;

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     * @param array<mixed> $orderData
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        return $orderData;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        throw new \LogicException('Legacy ' . static::class . ' is not registered as a payment handler and must not be used.');
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        throw new \LogicException('Legacy ' . static::class . ' is not registered as a payment handler and must not be used.');
    }
}
