<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Search;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use Mollie\Shopware\Component\Subscription\Search\SubscriptionAdminSearchIndexer;
use Mollie\Shopware\Unit\Fake\FakeConnection;
use Mollie\Shopware\Unit\Fake\FakeIteratorFactory;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[CoversClass(SubscriptionAdminSearchIndexer::class)]
final class SubscriptionAdminSearchIndexerTest extends TestCase
{
    private const SUBSCRIPTION_ID = '0189d6f0e5a5701fa0a5f0a1b2c3d4e5';

    private FakeConnection $connection;

    private FakeSubscriptionRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new FakeConnection();
        $this->repository = new FakeSubscriptionRepository();
    }

    public function testTheIndexerCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->buildIndexer()->getDecorated();
    }

    public function testTheIndexerIsRegisteredUnderItsOwnKey(): void
    {
        $indexer = $this->buildIndexer();

        self::assertSame('mollie_subscription', $indexer->getName());
        self::assertSame(SubscriptionDefinition::ENTITY_NAME, $indexer->getEntity());
    }

    public function testASubscriptionIsSearchableByItsMollieIdsAndDescription(): void
    {
        // The merchant searches the admin for the Mollie ids from the Mollie dashboard.
        $this->connection->withRows([
            [
                'id' => self::SUBSCRIPTION_ID,
                'mollie_id' => 'sub_ABC123',
                'mollie_customer_id' => 'cst_DEF456',
                'description' => 'Coffee Subscription',
                'next_payment_at' => null,
                'last_reminded_at' => null,
                'canceled_at' => null,
                'mandate_id' => 'mdt_GHI789',
                'status' => 'active',
            ],
        ]);

        $fetched = $this->buildIndexer()->fetch([self::SUBSCRIPTION_ID]);

        self::assertSame([
            self::SUBSCRIPTION_ID => [
                'id' => self::SUBSCRIPTION_ID,
                'text' => self::SUBSCRIPTION_ID . ' sub_abc123 cst_def456 coffee subscription mdt_ghi789 active',
            ],
        ], $fetched);
    }

    public function testNothingIsIndexedForSubscriptionsThatNoLongerExist(): void
    {
        self::assertSame([], $this->buildIndexer()->fetch([self::SUBSCRIPTION_ID]));
    }

    public function testTheSubscriptionsAreIteratedInTheConfiguredBatchSize(): void
    {
        $factory = new FakeIteratorFactory();

        (new SubscriptionAdminSearchIndexer($this->connection, $factory, $this->repository, 250))->getIterator();

        self::assertSame([['definition' => SubscriptionDefinition::ENTITY_NAME, 'limit' => 250]], $factory->getCreatedIterators());
    }

    public function testTheSearchResultIsResolvedIntoSubscriptionEntities(): void
    {
        $this->repository->add(SubscriptionEntityBuilder::create()->withId('subscription-id')->build());

        $globalData = $this->buildIndexer()->globalData(
            ['total' => 1, 'hits' => [['id' => 'subscription-id']]],
            Context::createDefaultContext()
        );

        self::assertSame(1, $globalData['total']);
        self::assertCount(1, $globalData['data']);
        self::assertSame('subscription-id', $globalData['data']->first()?->getId());
    }

    private function buildIndexer(): SubscriptionAdminSearchIndexer
    {
        return new SubscriptionAdminSearchIndexer($this->connection, new FakeIteratorFactory(), $this->repository, 100);
    }
}
