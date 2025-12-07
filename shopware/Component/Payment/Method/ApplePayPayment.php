<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class ApplePayPayment extends AbstractMolliePaymentHandler implements SubscriptionAwareInterface
{
    private ?string $applePayPaymentToken = null;

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::APPLEPAY;
    }

    public function getName(): string
    {
        return 'Apple Pay';
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment,RequestDataBag $dataBag,CustomerEntity $customer): CreatePayment
    {
        $payment->setApplePayPaymentToken($this->applePayPaymentToken);

        return $payment;
    }

    public function setApplePayPaymentToken(string $token): void
    {
        $this->applePayPaymentToken = $token;
    }
}
