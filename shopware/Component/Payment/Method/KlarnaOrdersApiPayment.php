<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\OrdersApiAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\TestOnlyAwareInterface;

final class KlarnaOrdersApiPayment extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface, DeprecatedMethodAwareInterface, OrdersApiAwareInterface, TestOnlyAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::KLARNA;
    }

    public function getName(): string
    {
        return 'Kl (Orders API - Test only)';
    }

    public function getTechnicalName(): string
    {
        return parent::getTechnicalName() . '_ordersapi';
    }
}
