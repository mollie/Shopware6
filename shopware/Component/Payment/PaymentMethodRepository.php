<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class PaymentMethodRepository
{
    /** @var AbstractMolliePaymentHandler[] */
    private array $paymentMethods;

    /**
     * @param AbstractMolliePaymentHandler[] $paymentMethods
     */
    public function __construct(array $paymentMethods)
    {
        foreach ($paymentMethods as $paymentMethod) {
            $this->addPaymentMethod($paymentMethod);
        }
    }

    public function addPaymentMethod(AbstractMolliePaymentHandler $paymentMethod): void
    {
        $this->paymentMethods[] = $paymentMethod;
    }

    /**
     * @return AbstractMolliePaymentHandler[]
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }
}
