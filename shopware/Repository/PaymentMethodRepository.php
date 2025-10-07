<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Kiener\MolliePayments\Handler\PaymentHandler;

final class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    private PaymentMethodCollection $paymentMethods;

    /**
     * @param array<PaymentHandler> $paymentMethods
     */
    public function __construct(array $paymentMethods)
    {
        $this->paymentMethods = new PaymentMethodCollection($paymentMethods);
    }

    public function getSubscriptionPaymentMethods(): PaymentMethodCollection
    {
        $collection = new PaymentMethodCollection($this->paymentMethods);

        return $collection->getSubscriptionPaymentMethods();
    }
}
