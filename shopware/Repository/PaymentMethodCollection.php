<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Shopware\Component\Payment\SubscriptionAware;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Struct\Collection;

final class PaymentMethodCollection extends Collection
{
    public function getNames(): array
    {
        $result = [];
        /** @var PaymentHandler $paymentMethod */
        foreach ($this as $paymentMethod) {
            $result[] = $paymentMethod->getPaymentMethod();
        }

        return $result;
    }

    public function getSubscriptionPaymentMethods(): self
    {
        return $this->filter(function (PaymentMethodEntity $paymentMethod) {
            return $paymentMethod instanceof SubscriptionAware;
        });
    }
}
