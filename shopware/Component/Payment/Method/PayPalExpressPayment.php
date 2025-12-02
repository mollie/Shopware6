<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Order\OrderEntity;

final class PayPalExpressPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'PayPal Express';
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        // TODO authenticationId
        return $payment;
    }

    public function getTechnicalName(): string
    {
        return parent::getTechnicalName() . 'express';
    }
}
