<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class PayPalPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'PayPal';
    }

    public function getDescription(): string
    {
        return 'Grow your business with PayPal’s worldwide brand recognition and Mollie’s seamless integration. With Mollie, you can add PayPal to your website in minutes. No hidden costs, monthly, or setup fees. Only pay when you get paid.';
    }
}
