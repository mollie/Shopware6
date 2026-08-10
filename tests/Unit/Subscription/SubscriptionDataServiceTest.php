<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\Exception\SubscriptionNotFoundException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionWithoutAddressException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionWithoutOrderException;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutCustomerException;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(SubscriptionDataService::class)]
#[CoversClass(SubscriptionNotFoundException::class)]
#[CoversClass(SubscriptionWithoutOrderException::class)]
#[CoversClass(SubscriptionWithoutAddressException::class)]
final class SubscriptionDataServiceTest extends TestCase
{
    public function testFindByIdReturnsStructWhenAllAssociationsArePresent(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->build()
        ;

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $service = $this->getService($repository);

        $result = $service->findById('subscription-id', Context::createDefaultContext());

        $this->assertSame($subscription, $result->getSubscription());
        $this->assertSame($subscription->getOrder(), $result->getOrder());
        $this->assertSame($subscription->getBillingAddress(), $result->getBillingAddress());
        $this->assertSame($subscription->getShippingAddress(), $result->getShippingAddress());
        $this->assertSame('Test', $result->getCustomer()->getFirstName());
    }

    public function testFindByIdThrowsWhenSubscriptionDoesNotExist(): void
    {
        $service = $this->getService(new FakeSubscriptionRepository());

        $this->expectException(SubscriptionNotFoundException::class);

        $service->findById('unknown-id', Context::createDefaultContext());
    }

    public function testFindByIdThrowsWhenSubscriptionHasNoOrder(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withoutOrder()
            ->build()
        ;

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $service = $this->getService($repository);

        $this->expectException(SubscriptionWithoutOrderException::class);

        $service->findById('subscription-id', Context::createDefaultContext());
    }

    public function testFindByIdThrowsWhenOrderHasNoCustomer(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withoutCustomer()
            ->build()
        ;

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $service = $this->getService($repository);

        $this->expectException(OrderWithoutCustomerException::class);

        $service->findById('subscription-id', Context::createDefaultContext());
    }

    public function testFindByIdThrowsWhenSubscriptionHasNoAddressAtAll(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withoutBillingAddress()
            ->withoutShippingAddress()
            ->build()
        ;

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $service = $this->getService($repository);

        $this->expectException(SubscriptionWithoutAddressException::class);

        $service->findById('subscription-id', Context::createDefaultContext());
    }

    public function testFindByIdFallsBackToShippingAddressWhenBillingAddressIsMissing(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withoutBillingAddress()
            ->build()
        ;

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $service = $this->getService($repository);

        $result = $service->findById('subscription-id', Context::createDefaultContext());

        $shippingAddress = $result->getShippingAddress();
        $this->assertSame($shippingAddress, $result->getBillingAddress());
        $this->assertSame($shippingAddress, $subscription->getBillingAddress());
        $this->assertSame($shippingAddress->getId(), $subscription->getBillingAddressId());
    }

    public function testFindByIdFallsBackToBillingAddressWhenShippingAddressIsMissing(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withoutShippingAddress()
            ->build()
        ;

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $service = $this->getService($repository);

        $result = $service->findById('subscription-id', Context::createDefaultContext());

        $billingAddress = $result->getBillingAddress();
        $this->assertSame($billingAddress, $result->getShippingAddress());
        $this->assertSame($billingAddress, $subscription->getShippingAddress());
        $this->assertSame($billingAddress->getId(), $subscription->getShippingAddressId());
    }

    private function getService(FakeSubscriptionRepository $repository): SubscriptionDataService
    {
        return new SubscriptionDataService($repository, new NullLogger());
    }
}
