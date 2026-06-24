<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Mollie\Shopware\Component\Payment\Handler\BusinessCustomerAwareInterface;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class BusinessCustomerPaymentMethodRemover extends AbstractPaymentRemover
{
    public function __construct(
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
    ) {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        if ($this->isBusinessCustomer($salesChannelContext->getCustomer()) === true) {
            return $paymentMethods;
        }

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodHandler = $this->paymentHandlerLocator->findByIdentifier($paymentMethod->getHandlerIdentifier());
            if (! $paymentMethodHandler instanceof BusinessCustomerAwareInterface) {
                continue;
            }

            $paymentMethods->remove($paymentMethod->getId());
        }

        return $paymentMethods;
    }

    private function isBusinessCustomer(?CustomerEntity $customer): bool
    {
        if ($customer === null) {
            return false;
        }

        $billingAddress = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        if (! $billingAddress instanceof CustomerAddressEntity) {
            return false;
        }

        $company = $billingAddress->getCompany();
        if ($company === null) {
            return false;
        }

        return mb_strlen($company) > 0;
    }
}
