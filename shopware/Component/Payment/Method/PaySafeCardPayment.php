<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentParameterInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class PaySafeCardPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAY_SAFE_CARD;
    }

    public function getName(): string
    {
        return 'paysafecard';
    }

    public function applyPaymentSpecificParameters(PaymentParameterInterface $payment, RequestDataBag $dataBag,CustomerEntity $customer): PaymentParameterInterface
    {
        $payment->setCustomerReference($customer->getCustomerNumber());

        return $payment;
    }
}
