<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BusinessCustomerAwareInterface;

final class FakeBusinessCustomerAwarePaymentHandler extends AbstractMolliePaymentHandler implements BusinessCustomerAwareInterface
{
    public function __construct()
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BILLIE;
    }

    public function getName(): string
    {
        return 'fake_business_customer_aware_handler';
    }
}
