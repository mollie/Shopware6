<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Order\OrderEntity;

final class ApplePayPayment extends AbstractMolliePaymentHandler
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

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        $payment->setApplePayPaymentToken($this->applePayPaymentToken);

        return $payment;
    }

    public function setApplePayPaymentToken(string $token): void
    {
        $this->applePayPaymentToken = $token;
    }
}
