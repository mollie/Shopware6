<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(SubscriptionDataStruct::class)]
final class SubscriptionDataStructTest extends TestCase
{
    public function testGettersReturnConstructorArguments(): void
    {
        $subscription = new SubscriptionEntity();
        $order = new OrderEntity();
        $customer = new CustomerEntity();
        $billingAddress = new SubscriptionAddressEntity();
        $shippingAddress = new SubscriptionAddressEntity();

        $struct = new SubscriptionDataStruct($subscription, $order, $customer, $billingAddress, $shippingAddress);

        $this->assertSame($subscription, $struct->getSubscription());
        $this->assertSame($order, $struct->getOrder());
        $this->assertSame($customer, $struct->getCustomer());
        $this->assertSame($billingAddress, $struct->getBillingAddress());
        $this->assertSame($shippingAddress, $struct->getShippingAddress());
    }
}
