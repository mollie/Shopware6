<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class SubscriptionDataStruct
{
    public function __construct(
        private readonly SubscriptionEntity $subscription,
        private readonly OrderEntity $order,
        private readonly CustomerEntity $customer,
        private readonly SubscriptionAddressEntity $billingAddress,
        private readonly SubscriptionAddressEntity $shippingAddress
    ) {
    }

    public function getSubscription(): SubscriptionEntity
    {
        return $this->subscription;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getBillingAddress(): SubscriptionAddressEntity
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): SubscriptionAddressEntity
    {
        return $this->shippingAddress;
    }
}
