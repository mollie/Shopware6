<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class BancomatPayPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BANCOMAT_PAY;
    }

    public function getName(): string
    {
        return 'Bancomat Pay';
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, RequestDataBag $dataBag, CustomerEntity $customer): CreatePayment
    {
        $billingAddress = $payment->getBillingAddress();
        $phoneNumber = $dataBag->get('molliePayPhone',$billingAddress->getPhone());
        $billingAddress->setPhone($phoneNumber);

        return $payment;
    }
}
