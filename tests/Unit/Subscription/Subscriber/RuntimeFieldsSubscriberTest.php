<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Subscriber;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Subscriber\RuntimeFieldsSubscriber;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

#[CoversClass(RuntimeFieldsSubscriber::class)]
final class RuntimeFieldsSubscriberTest extends TestCase
{
    public function testCancelUntilFallsBackToNextPaymentWhenNoCancellationDays(): void
    {
        $subscription = $this->buildSubscription(new \DateTimeImmutable('2026-06-15 10:00:00'));

        $subscriber = new RuntimeFieldsSubscriber(
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true, cancelDays: 0))
        );
        $subscriber->onSubscriptionsLoaded($this->buildEvent([$subscription]));

        $this->assertEquals(new \DateTimeImmutable('2026-06-15 10:00:00'), $subscription->getCancelUntil());
    }

    public function testCancelUntilSubtractsCancellationDaysFromNextPayment(): void
    {
        $subscription = $this->buildSubscription(new \DateTimeImmutable('2026-06-15 10:00:00'));

        $subscriber = new RuntimeFieldsSubscriber(
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true, cancelDays: 5))
        );
        $subscriber->onSubscriptionsLoaded($this->buildEvent([$subscription]));

        $this->assertEquals(new \DateTimeImmutable('2026-06-10 10:00:00'), $subscription->getCancelUntil());
    }

    public function testSubscriptionWithoutNextPaymentLeavesCancelUntilUntouched(): void
    {
        $subscription = SubscriptionEntityBuilder::create()->build();
        $subscription->setNextPaymentAt(null);

        $subscriber = new RuntimeFieldsSubscriber(
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true, cancelDays: 5))
        );
        $subscriber->onSubscriptionsLoaded($this->buildEvent([$subscription]));

        $this->assertNull($subscription->getCancelUntil());
    }

    private function buildSubscription(\DateTimeInterface $nextPaymentAt): SubscriptionEntity
    {
        $subscription = SubscriptionEntityBuilder::create()->build();
        $subscription->setNextPaymentAt($nextPaymentAt);

        return $subscription;
    }

    /**
     * @param list<SubscriptionEntity> $subscriptions
     *
     * @return EntityLoadedEvent<SubscriptionEntity>
     */
    private function buildEvent(array $subscriptions): EntityLoadedEvent
    {
        return new EntityLoadedEvent(
            new SubscriptionDefinition(),
            $subscriptions,
            Context::createDefaultContext()
        );
    }
}
