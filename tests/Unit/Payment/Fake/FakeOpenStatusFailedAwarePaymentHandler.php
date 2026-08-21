<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\OpenStatusFailedAwareInterface;

final class FakeOpenStatusFailedAwarePaymentHandler extends AbstractMolliePaymentHandler implements OpenStatusFailedAwareInterface
{
    public function __construct()
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BAN_CONTACT;
    }

    public function getName(): string
    {
        return 'Fake open status failed handler';
    }
}
