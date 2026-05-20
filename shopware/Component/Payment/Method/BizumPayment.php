<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentParameterInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class BizumPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BIZUM;
    }

    public function getName(): string
    {
        return 'Bizum';
    }

    public function applyPaymentSpecificParameters(PaymentParameterInterface $payment,RequestDataBag $dataBag, CustomerEntity $customer): PaymentParameterInterface
    {
        $billingAddress = $payment->getBillingAddress();
        $phoneNumber = $dataBag->get('molliePayPhone',$billingAddress->getPhone());
        $billingAddress->setPhone($phoneNumber);

        return $payment;
    }
}
