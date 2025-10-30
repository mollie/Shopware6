<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;

final class PaymentMethodRepository
{
    /** @var CompatibilityPaymentHandler[] */
    private array $paymentMethods;

    public function __construct(array $paymentMethods)
    {
        foreach ($paymentMethods as $paymentMethod) {
            $this->addPaymentMethod($paymentMethod);
        }
    }

    public function addPaymentMethod(CompatibilityPaymentHandler $paymentMethod): void
    {
        $this->paymentMethods[] = $paymentMethod;
    }
}
