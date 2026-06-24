<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Builder;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class SubscriptionEntityBuilder
{
    private string $id = 'subscription-id';
    private string $mollieId = 'sub_test123';
    private string $mollieCustomerId = 'cst_test123';
    private string $salesChannelId = 'sales-channel-id';
    private string $orderId = 'order-id';
    private SubscriptionStatus $status = SubscriptionStatus::ACTIVE;
    private ?SubscriptionMetadata $metadata = null;
    private ?SubscriptionAddressEntity $billingAddressOverride = null;
    private ?SubscriptionAddressEntity $shippingAddressOverride = null;
    private string $customerId = 'customer-id';
    private bool $withOrder = true;
    private bool $withCustomer = true;
    private bool $withBillingAddress = true;
    private bool $withShippingAddress = true;

    public static function create(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withMollieId(string $mollieId): self
    {
        $this->mollieId = $mollieId;

        return $this;
    }

    public function withStatus(SubscriptionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withMetadata(SubscriptionMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function withCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function withBillingAddress(SubscriptionAddressEntity $address): self
    {
        $this->billingAddressOverride = $address;
        $this->withBillingAddress = true;

        return $this;
    }

    public function withShippingAddress(SubscriptionAddressEntity $address): self
    {
        $this->shippingAddressOverride = $address;
        $this->withShippingAddress = true;

        return $this;
    }

    public function withoutOrder(): self
    {
        $this->withOrder = false;

        return $this;
    }

    public function withoutCustomer(): self
    {
        $this->withCustomer = false;

        return $this;
    }

    public function withoutBillingAddress(): self
    {
        $this->withBillingAddress = false;

        return $this;
    }

    public function withoutShippingAddress(): self
    {
        $this->withShippingAddress = false;

        return $this;
    }

    public function build(): SubscriptionEntity
    {
        $entity = new SubscriptionEntity();
        $entity->setId($this->id);
        $entity->setCustomerId($this->customerId);
        $entity->setMollieId($this->mollieId);
        $entity->setMollieCustomerId($this->mollieCustomerId);
        $entity->setSalesChannelId($this->salesChannelId);
        $entity->setOrderId($this->orderId);
        $entity->setOrderVersionId('order-version-id');
        $entity->setStatus($this->status->value);
        $entity->setMetadata($this->metadata ?? new SubscriptionMetadata('2026-01-01', 1, IntervalUnit::MONTHS));

        if ($this->withOrder) {
            $entity->setOrder($this->buildOrder());
        }

        if ($this->withBillingAddress) {
            $entity->setBillingAddress($this->billingAddressOverride ?? $this->buildAddress('billing'));
        }

        if ($this->withShippingAddress) {
            $entity->setShippingAddress($this->shippingAddressOverride ?? $this->buildAddress('shipping'));
        }

        return $entity;
    }

    private function buildOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($this->orderId);
        $order->setOrderNumber('10000');
        $order->setSalesChannelId($this->salesChannelId);

        if ($this->withCustomer) {
            $defaultBillingAddress = new CustomerAddressEntity();
            $defaultBillingAddress->setId('default-billing-address-id');

            $customer = CustomerBuilder::create()
                ->withDefaultBillingAddress($defaultBillingAddress)
                ->build()
            ;

            $orderCustomer = new OrderCustomerEntity();
            $orderCustomer->setUniqueIdentifier('order-customer-id');
            $orderCustomer->setCustomerId('customer-id');
            $orderCustomer->setCustomer($customer);

            $order->setOrderCustomer($orderCustomer);
        }

        return $order;
    }

    private function buildAddress(string $type): SubscriptionAddressEntity
    {
        return SubscriptionAddressBuilder::create()
            ->withId($type . '-address-id')
            ->withSubscriptionId($this->id)
            ->build()
        ;
    }
}
