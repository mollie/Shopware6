<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Event;

use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionEndedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPausedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPriceChangeNoticeEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRenewedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionResumedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionSkippedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionStartedEvent;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(SubscriptionActionEvent::class)]
#[CoversClass(SubscriptionCancelledEvent::class)]
#[CoversClass(SubscriptionEndedEvent::class)]
#[CoversClass(SubscriptionPausedEvent::class)]
#[CoversClass(SubscriptionPriceChangeNoticeEvent::class)]
#[CoversClass(SubscriptionRenewedEvent::class)]
#[CoversClass(SubscriptionResumedEvent::class)]
#[CoversClass(SubscriptionSkippedEvent::class)]
#[CoversClass(SubscriptionStartedEvent::class)]
final class SubscriptionEventsTest extends TestCase
{
    public function testEventExposesSubscriptionFromConstructor(): void
    {
        $event = $this->buildEvent(SubscriptionCancelledEvent::class);

        $this->assertSame('subscription-id', $event->getSubscription()->getId());
        $this->assertSame('subscription-id', $event->getSubscriptionId());
    }

    public function testEventExposesCustomerFromConstructor(): void
    {
        $customer = $this->buildCustomer('test@example.com', 'Jane', 'Doe');
        $event = $this->buildEvent(SubscriptionCancelledEvent::class, customer: $customer);

        $this->assertSame($customer, $event->getCustomer());
    }

    public function testEventExposesContextFromConstructor(): void
    {
        $context = Context::createDefaultContext();
        $event = $this->buildEvent(SubscriptionCancelledEvent::class, context: $context);

        $this->assertSame($context, $event->getContext());
    }

    public function testGetSalesChannelIdDelegatesToSubscription(): void
    {
        $subscription = SubscriptionEntityBuilder::create()->withId('subscription-id')->build();
        $subscription->setSalesChannelId('custom-sales-channel-id');

        $event = new SubscriptionCancelledEvent($subscription, $this->buildCustomer(), Context::createDefaultContext());

        $this->assertSame('custom-sales-channel-id', $event->getSalesChannelId());
    }

    public function testGetMailStructFormatsCustomerNameAndEmail(): void
    {
        $customer = $this->buildCustomer('jane@example.com', 'Jane', 'Doe');
        $event = $this->buildEvent(SubscriptionCancelledEvent::class, customer: $customer);

        $this->assertSame(['jane@example.com' => 'Jane Doe'], $event->getMailStruct()->getRecipients());
    }

    public function testGetAvailableDataExposesSubscriptionAndCustomerKeys(): void
    {
        $data = SubscriptionActionEvent::getAvailableData()->toArray();

        $this->assertArrayHasKey('subscription', $data);
        $this->assertArrayHasKey('customer', $data);
    }

    /**
     * @param class-string<SubscriptionActionEvent> $eventClass
     */
    #[DataProvider('concreteEventNameProvider')]
    public function testConcreteEventReturnsExpectedName(string $eventClass, string $expectedName): void
    {
        $event = $this->buildEvent($eventClass);

        $this->assertSame($expectedName, $event->getName());
    }

    /**
     * @return array<string,array{0:class-string<SubscriptionActionEvent>,1:string}>
     */
    public static function concreteEventNameProvider(): array
    {
        return [
            'cancelled' => [SubscriptionCancelledEvent::class, 'mollie.subscription.cancelled'],
            'ended' => [SubscriptionEndedEvent::class, 'mollie.subscription.ended'],
            'paused' => [SubscriptionPausedEvent::class, 'mollie.subscription.paused'],
            'priceChangeNotice' => [SubscriptionPriceChangeNoticeEvent::class, 'mollie.subscription.priceChangeNotice'],
            'renewed' => [SubscriptionRenewedEvent::class, 'mollie.subscription.renewed'],
            'resumed' => [SubscriptionResumedEvent::class, 'mollie.subscription.resumed'],
            'skipped' => [SubscriptionSkippedEvent::class, 'mollie.subscription.skipped'],
            'started' => [SubscriptionStartedEvent::class, 'mollie.subscription.started'],
        ];
    }

    /**
     * @param class-string<SubscriptionActionEvent> $eventClass
     */
    private function buildEvent(string $eventClass, ?CustomerEntity $customer = null, ?Context $context = null): SubscriptionActionEvent
    {
        $subscription = SubscriptionEntityBuilder::create()->withId('subscription-id')->build();

        return new $eventClass(
            $subscription,
            $customer ?? $this->buildCustomer(),
            $context ?? Context::createDefaultContext()
        );
    }

    private function buildCustomer(string $email = 'test@example.com', string $firstName = 'Test', string $lastName = 'Customer'): CustomerEntity
    {
        return CustomerBuilder::create()
            ->withEmail($email)
            ->withFirstName($firstName)
            ->withLastName($lastName)
            ->build()
        ;
    }
}
