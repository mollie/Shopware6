<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class PaymentHandlerLocator
{
    /** @var AbstractMolliePaymentHandler[] */
    private array $paymentMethods = [];

    /**
     * @param AbstractMolliePaymentHandler[] $paymentMethods
     */
    public function __construct(
        #[AutowireIterator('mollie.payment.method')]
        iterable $paymentMethods
    ) {
        foreach ($paymentMethods as $paymentMethod) {
            $this->addPaymentMethod($paymentMethod);
        }
    }

    /**
     * @return AbstractMolliePaymentHandler[]
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    public function findByPaymentMethod(string $paymentMethodName): ?AbstractMolliePaymentHandler
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            if ($paymentMethod->getPaymentMethod()->value === $paymentMethodName) {
                return $paymentMethod;
            }
        }

        return null;
    }

    public function findByIdentifier(string $paymentHandlerIdentifier): ?AbstractMolliePaymentHandler
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            if (get_class($paymentMethod) === $paymentHandlerIdentifier) {
                return $paymentMethod;
            }
        }

        return null;
    }

    private function addPaymentMethod(AbstractMolliePaymentHandler $paymentMethod): void
    {
        $this->paymentMethods[] = $paymentMethod;
    }
}
