<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class FakePaymentMethodHandler extends AbstractMolliePaymentHandler
{
    public function __construct()
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::PAYPAL;
    }

    public function getName(): string
    {
        return 'Fake payment method';
    }
}
