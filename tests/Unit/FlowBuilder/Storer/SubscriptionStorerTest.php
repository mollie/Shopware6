<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Storer;

use Mollie\Shopware\Component\FlowBuilder\Storer\SubscriptionStorer;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionAware;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRemindedEvent;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeFlowEvent;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(SubscriptionStorer::class)]
final class SubscriptionStorerTest extends TestCase
{
    public function testStoreSavesSubscriptionId(): void
    {
        $storer = new SubscriptionStorer(new FakeSubscriptionRepository());
        $event = new SubscriptionRemindedEvent(
            SubscriptionEntityBuilder::create()->withId('subscription-id')->build(),
            CustomerBuilder::create()->build(),
            Context::createDefaultContext()
        );

        $stored = $storer->store($event, []);

        $this->assertSame('subscription-id', $stored[SubscriptionAware::STORAGE_KEY_SUBSCRIPTION]);
    }

    public function testStoreIgnoresNonSubscriptionEvent(): void
    {
        $storer = new SubscriptionStorer(new FakeSubscriptionRepository());

        $event = new FakeFlowEvent();

        $this->assertSame([], $storer->store($event, []));
    }

    public function testRestoreLoadsSubscriptionAsData(): void
    {
        $repository = new FakeSubscriptionRepository();
        $repository->add(SubscriptionEntityBuilder::create()->withId('subscription-id')->build());

        $storer = new SubscriptionStorer($repository);

        $flow = new \Shopware\Core\Content\Flow\Dispatching\StorableFlow(
            'mollie.subscription.renewal_reminder',
            Context::createDefaultContext(),
            [SubscriptionAware::STORAGE_KEY_SUBSCRIPTION => 'subscription-id'],
            []
        );

        $storer->restore($flow);

        $subscription = $flow->getData('subscription');
        $this->assertInstanceOf(SubscriptionEntity::class, $subscription);
        $this->assertSame('subscription-id', $subscription->getId());
    }
}
