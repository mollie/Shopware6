<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Kiener\MolliePayments\Handler\PaymentHandler;

final class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    private PaymentMethodCollection $paymentMethods;

    /**
     * @param PaymentHandler[] $paymentMethods
     */
    public function __construct(iterable $paymentMethods)
    {
        $this->paymentMethods = new PaymentMethodCollection($paymentMethods);
    }

    public function getSubscriptionPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods->getSubscriptionPaymentMethods();
    }
}
