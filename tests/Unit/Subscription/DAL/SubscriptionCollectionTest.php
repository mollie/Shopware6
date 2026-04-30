<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SubscriptionCollection::class)]
final class SubscriptionCollectionTest extends TestCase
{
    public function testGetFlatListReturnsEmptyArrayForEmptyCollection(): void
    {
        $collection = new SubscriptionCollection();

        $this->assertCount(0, $collection->getFlatList());
    }

    public function testGetFlatListReindexesEntitiesAsZeroBasedList(): void
    {
        $first = new SubscriptionEntity();
        $first->setId(Uuid::randomHex());
        $second = new SubscriptionEntity();
        $second->setId(Uuid::randomHex());

        $collection = new SubscriptionCollection([$first, $second]);

        $flatList = $collection->getFlatList();

        $this->assertCount(2, $flatList);
        $this->assertSame([0, 1], array_keys($flatList));
        $this->assertSame($first, $flatList[0]);
        $this->assertSame($second, $flatList[1]);
    }

    public function testFilterByStatusReturnsOnlyEntitiesWithMatchingStatus(): void
    {
        $active = $this->buildSubscription(SubscriptionStatus::ACTIVE);
        $paused = $this->buildSubscription(SubscriptionStatus::PAUSED);
        $alsoActive = $this->buildSubscription(SubscriptionStatus::ACTIVE);
        $cancelled = $this->buildSubscription(SubscriptionStatus::CANCELED);

        $collection = new SubscriptionCollection([$active, $paused, $alsoActive, $cancelled]);

        $filtered = $collection->filterByStatus(SubscriptionStatus::ACTIVE->value);

        $this->assertCount(2, $filtered);
        $this->assertContains($active, $filtered->getFlatList());
        $this->assertContains($alsoActive, $filtered->getFlatList());
    }

    public function testFilterByStatusReturnsNewEmptyCollectionWhenNothingMatches(): void
    {
        $collection = new SubscriptionCollection([
            $this->buildSubscription(SubscriptionStatus::ACTIVE),
        ]);

        $filtered = $collection->filterByStatus(SubscriptionStatus::PAUSED->value);

        $this->assertCount(0, $filtered);
    }

    public function testFilterByStatusReturnsNewCollectionInstance(): void
    {
        $collection = new SubscriptionCollection();

        $filtered = $collection->filterByStatus(SubscriptionStatus::ACTIVE->value);

        $this->assertInstanceOf(SubscriptionCollection::class, $filtered);
        $this->assertNotSame($collection, $filtered);
    }

    private function buildSubscription(SubscriptionStatus $status): SubscriptionEntity
    {
        $subscription = new SubscriptionEntity();
        $subscription->setId(Uuid::randomHex());
        $subscription->setStatus($status->value);

        return $subscription;
    }
}
