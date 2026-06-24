<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\DeprecatedMethodAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\OrdersApiAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\TestOnlyAwareInterface;

/**
 * Test-only payment method. Uses Mollie Orders API (/v2/orders) so that
 * Behat tests can create legacy Orders-API transactions and verify that
 * the refund flow handles them correctly.
 *
 * Implements DeprecatedMethodAwareInterface so the installer keeps it
 * inactive in production; Behat fixtures activate it explicitly.
 */
final class PayPalOrdersApiPayment extends AbstractMolliePaymentHandler implements DeprecatedMethodAwareInterface, OrdersApiAwareInterface, TestOnlyAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'PP (Orders API - Test only)';
    }

    public function getTechnicalName(): string
    {
        return parent::getTechnicalName() . '_ordersapi';
    }
}
