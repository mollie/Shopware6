<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentParameterInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\OrdersApiAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\RecurringAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class PayPalExpressPayment extends AbstractMolliePaymentHandler implements SubscriptionAwareInterface, RecurringAwareInterface, OrdersApiAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'PayPal Express';
    }

    public function applyPaymentSpecificParameters(PaymentParameterInterface $payment,RequestDataBag $dataBag, CustomerEntity $customer): PaymentParameterInterface
    {
        $authenticationId = $dataBag->get('authenticationId');
        if ($authenticationId !== null) {
            $payment->setAuthenticationId($authenticationId);
        }

        return $payment;
    }

    public function getTechnicalName(): string
    {
        return parent::getTechnicalName() . 'express';
    }
}
