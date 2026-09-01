<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRemindedEvent;
use Mollie\Shopware\Component\Subscription\ReminderValidator;
use Mollie\Shopware\Component\Subscription\SubscriptionRenewalReminder;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(SubscriptionRenewalReminder::class)]
final class SubscriptionRenewalReminderTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private EventSpy $eventSpy;
    private FakeSubscriptionRepository $subscriptionRepository;
    private FakeCustomerRepository $customerRepository;
    private FakeSalesChannelRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->eventSpy = new EventSpy();
        $this->subscriptionRepository = new FakeSubscriptionRepository();
        $this->customerRepository = new FakeCustomerRepository();
        $this->salesChannelRepository = new FakeSalesChannelRepository();
        $this->salesChannelRepository->add($this->makeSalesChannel());
    }

    public function testNothingIsRemindedWithoutASalesChannel(): void
    {
        $reminder = new SubscriptionRenewalReminder(
            new FakeSalesChannelRepository(),
            $this->subscriptionRepository,
            $this->customerRepository,
            new FakeSettingsService(subscriptionSettings: $this->enabledSettings()),
            new ReminderValidator(),
            $this->eventSpy,
            new FakeLogger()
        );

        $this->assertSame(0, $reminder->remind(new Context(new SystemSource())));
    }

    public function testNothingIsRemindedWhileSubscriptionsAreDisabled(): void
    {
        $this->subscriptionRepository->add($this->makeDueSubscription());

        $reminded = $this->makeReminder(new SubscriptionSettings(enabled: false, reminderDays: 7))->remind(new Context(new SystemSource()));

        $this->assertSame(0, $reminded);
        $this->assertSame(0, $this->eventSpy->getEventCount());
    }

    public function testDueSubscriptionRaisesAReminderEvent(): void
    {
        $subscription = $this->makeDueSubscription();
        $this->subscriptionRepository->add($subscription);
        $this->customerRepository->add(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());

        $reminded = $this->makeReminder()->remind(new Context(new SystemSource()));

        $event = $this->eventSpy->getEvent();

        $this->assertSame(1, $reminded);
        $this->assertInstanceOf(SubscriptionRemindedEvent::class, $event);
    }

    public function testReminderIsRecordedOnTheSubscription(): void
    {
        $this->subscriptionRepository->add($this->makeDueSubscription());
        $this->customerRepository->add(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());
        $beforeTheReminder = new \DateTimeImmutable();

        $this->makeReminder()->remind(new Context(new SystemSource()));

        $upsert = $this->subscriptionRepository->getLastUpsert();

        $this->assertSame('subscription-id', $upsert['id']);
        $this->assertGreaterThanOrEqual($beforeTheReminder, $upsert['lastRemindedAt']);
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $upsert['lastRemindedAt']);
        $this->assertSame('reminded about renewal', $upsert['historyEntries'][0]['comment']);
    }

    public function testPausedSubscriptionIsNotReminded(): void
    {
        $this->subscriptionRepository->add($this->makeDueSubscription(SubscriptionStatus::PAUSED));
        $this->customerRepository->add(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());

        $reminded = $this->makeReminder()->remind(new Context(new SystemSource()));

        $this->assertSame(0, $reminded);
        $this->assertSame(0, $this->eventSpy->getEventCount());
    }

    public function testResumedSubscriptionIsReminded(): void
    {
        $this->subscriptionRepository->add($this->makeDueSubscription(SubscriptionStatus::RESUMED));
        $this->customerRepository->add(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());

        $this->assertSame(1, $this->makeReminder()->remind(new Context(new SystemSource())));
    }

    public function testSubscriptionOutsideTheReminderWindowIsNotReminded(): void
    {
        $subscription = $this->makeDueSubscription();
        $subscription->setNextPaymentAt(new \DateTime('+60 days'));
        $this->subscriptionRepository->add($subscription);
        $this->customerRepository->add(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());

        $reminded = $this->makeReminder()->remind(new Context(new SystemSource()));

        $this->assertSame(0, $reminded);
        $this->assertSame(0, $this->eventSpy->getEventCount());
    }

    public function testAlreadyRemindedSubscriptionIsNotRemindedTwice(): void
    {
        $subscription = $this->makeDueSubscription();
        $subscription->setLastRemindedAt(new \DateTime('now'));
        $this->subscriptionRepository->add($subscription);
        $this->customerRepository->add(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());

        $reminded = $this->makeReminder()->remind(new Context(new SystemSource()));

        $this->assertSame(0, $reminded);
        $this->assertSame(0, $this->eventSpy->getEventCount());
    }

    public function testSubscriptionWithoutAShopwareCustomerIsSkipped(): void
    {
        $this->subscriptionRepository->add($this->makeDueSubscription());

        $reminded = $this->makeReminder()->remind(new Context(new SystemSource()));

        $this->assertSame(0, $reminded);
        $this->assertSame(0, $this->eventSpy->getEventCount());
        $this->assertSame(0, $this->subscriptionRepository->getUpsertCount());
    }

    private function makeReminder(?SubscriptionSettings $settings = null): SubscriptionRenewalReminder
    {
        return new SubscriptionRenewalReminder(
            $this->salesChannelRepository,
            $this->subscriptionRepository,
            $this->customerRepository,
            new FakeSettingsService(subscriptionSettings: $settings ?? $this->enabledSettings()),
            new ReminderValidator(),
            $this->eventSpy,
            new FakeLogger()
        );
    }

    private function enabledSettings(): SubscriptionSettings
    {
        return new SubscriptionSettings(enabled: true, reminderDays: 7);
    }

    private function makeDueSubscription(SubscriptionStatus $status = SubscriptionStatus::ACTIVE): SubscriptionEntity
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withStatus($status)
            ->withCustomerId(self::CUSTOMER_ID)
            ->build()
        ;
        $subscription->setNextPaymentAt(new \DateTime('+3 days'));

        return $subscription;
    }

    private function makeSalesChannel(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(self::SALES_CHANNEL_ID);
        $salesChannel->setName('Storefront');

        return $salesChannel;
    }
}
