<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;

final class FakeSubscriptionAwarePaymentHandler extends AbstractMolliePaymentHandler implements SubscriptionAwareInterface
{
    public function __construct()
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function getName(): string
    {
        return 'fake_subscription_aware_handler';
    }
}
