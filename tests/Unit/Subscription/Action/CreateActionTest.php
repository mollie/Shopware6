<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\Action\CreateAction;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(CreateAction::class)]
final class CreateActionTest extends TestCase
{
    public function testCreatePersistsPendingSubscriptionWithExpectedFields(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $action = new CreateAction($repository, new NullLogger());

        $customer = CustomerBuilder::create()->withId('customer-id')->build();
        $order = $this->buildOrder();
        $primaryLineItem = $this->buildSubscriptionLineItem(1, IntervalUnit::MONTHS);
        $billing = $this->buildAddress();
        $shipping = $this->buildAddress();

        $action->create($order, $primaryLineItem, $customer, $billing, $shipping, 99.99, $context);

        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame('customer-id', $payload['customerId']);
        $this->assertNull($payload['mollieCustomerId']);
        $this->assertNull($payload['mollieSubscriptionId']);
        $this->assertSame(SubscriptionStatus::PENDING->value, $payload['status']);
        $this->assertSame(99.99, $payload['amount']);
        $this->assertSame('order-id', $payload['orderId']);
        $this->assertStringStartsWith('Order #10000', $payload['description']);
        $this->assertStringContainsString('1 months', $payload['description']);
        $this->assertSame('created', $payload['historyEntries'][0]['comment']);
        $this->assertSame(SubscriptionStatus::PENDING->value, $payload['historyEntries'][0]['statusTo']);
        $this->assertSame(SubscriptionTag::ID, $payload['order']['tags'][0]['id']);
    }

    public function testCreateBuildsBillingAndShippingAddressesWithSubscriptionId(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $action = new CreateAction($repository, new NullLogger());

        $action->create(
            $this->buildOrder(),
            $this->buildSubscriptionLineItem(1, IntervalUnit::MONTHS),
            CustomerBuilder::create()->build(),
            $this->buildAddress('Billing Street'),
            $this->buildAddress('Shipping Street'),
            10.00,
            $context
        );

        $payload = $repository->getLastUpsert();
        $this->assertSame($payload['id'], $payload['billingAddress']['subscriptionId']);
        $this->assertSame($payload['id'], $payload['shippingAddress']['subscriptionId']);
        $this->assertSame('Billing Street', $payload['billingAddress']['street']);
        $this->assertSame('Shipping Street', $payload['shippingAddress']['street']);
    }

    public function testCreateMetadataReducesRepetitionByOne(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $action = new CreateAction($repository, new NullLogger());

        $primaryLineItem = $this->buildSubscriptionLineItem(1, IntervalUnit::MONTHS, repetition: 5);

        $action->create(
            $this->buildOrder(),
            $primaryLineItem,
            CustomerBuilder::create()->build(),
            $this->buildAddress(),
            $this->buildAddress(),
            10.00,
            $context
        );

        $payload = $repository->getLastUpsert();
        $this->assertSame(4, $payload['metadata']['times']);
    }

    public function testCreateReturnsEmptyMetadataWhenLineItemHasNoMollieExtension(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $action = new CreateAction($repository, new NullLogger());

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('line-item-1');
        $lineItem->setLabel('Plain product');

        $action->create(
            $this->buildOrder(),
            $lineItem,
            CustomerBuilder::create()->build(),
            $this->buildAddress(),
            $this->buildAddress(),
            10.00,
            $context
        );

        $payload = $repository->getLastUpsert();
        $this->assertSame([], $payload['metadata']);
        $this->assertSame('Order #10000', $payload['description']);
    }

    private function buildOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setVersionId('order-version-id');
        $order->setOrderNumber('10000');
        $order->setOrderDate(new \DateTimeImmutable('2026-04-30'));
        $order->setSalesChannelId('sales-channel-id');
        $order->setCurrencyId('currency-id');
        $order->setAmountTotal(99.99);
        $order->setAmountNet(83.99);
        $order->setTaxStatus('gross');

        return $order;
    }

    private function buildSubscriptionLineItem(int $intervalValue, IntervalUnit $intervalUnit, int $repetition = 0): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('line-item-1');
        $lineItem->setLabel('Subscription product');

        $product = new Product();
        $product->setIsSubscription(true);
        $product->setInterval(new Interval($intervalValue, $intervalUnit));
        $product->setRepetition($repetition);
        $lineItem->addExtension(Mollie::EXTENSION, $product);

        return $lineItem;
    }

    private function buildAddress(string $street = 'Default Street 1'): OrderAddressEntity
    {
        $address = new OrderAddressEntity();
        $address->setSalutationId('salutation-id');
        $address->setFirstName('Test');
        $address->setLastName('Customer');
        $address->setStreet($street);
        $address->setZipcode('12345');
        $address->setCity('Berlin');
        $address->setCountryId('country-id');

        return $address;
    }
}
