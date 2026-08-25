<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Search;

use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\Search\RefundAdminSearchIndexer;
use Mollie\Shopware\Unit\Fake\FakeConnection;
use Mollie\Shopware\Unit\Fake\FakeIteratorFactory;
use Mollie\Shopware\Unit\Refund\Fake\FakeRefundRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[CoversClass(RefundAdminSearchIndexer::class)]
final class RefundAdminSearchIndexerTest extends TestCase
{
    private const REFUND_ID = '0189d6f0e5a5701fa0a5f0a1b2c3d4e5';

    private FakeConnection $connection;

    private FakeRefundRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new FakeConnection();
        $this->repository = new FakeRefundRepository();
    }

    public function testTheIndexerCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->buildIndexer()->getDecorated();
    }

    public function testTheIndexerIsRegisteredUnderItsOwnKey(): void
    {
        $indexer = $this->buildIndexer();

        static::assertSame('mollie_refund', $indexer->getName());
        static::assertSame(RefundDefinition::ENTITY_NAME, $indexer->getEntity());
    }

    public function testARefundIsSearchableByEveryDescriptionItCarries(): void
    {
        $this->connection->withRows([
            ['id' => self::REFUND_ID, 'type' => 'full', 'public_description' => 'Wrong Size', 'internal_description' => 'Customer complaint'],
        ]);

        $fetched = $this->buildIndexer()->fetch([self::REFUND_ID]);

        static::assertSame([
            self::REFUND_ID => [
                'id' => self::REFUND_ID,
                'text' => self::REFUND_ID . ' full wrong size customer complaint',
            ],
        ], $fetched);
    }

    public function testARefundWithoutDescriptionsIsStillSearchableByItsType(): void
    {
        $this->connection->withRows([
            ['id' => self::REFUND_ID, 'type' => 'partial', 'public_description' => null, 'internal_description' => ''],
        ]);

        $fetched = $this->buildIndexer()->fetch([self::REFUND_ID]);

        static::assertSame([self::REFUND_ID => ['id' => self::REFUND_ID, 'text' => self::REFUND_ID . ' partial']], $fetched);
    }

    public function testNothingIsIndexedForRefundsThatNoLongerExist(): void
    {
        static::assertSame([], $this->buildIndexer()->fetch([self::REFUND_ID]));
    }

    public function testTheRefundsAreIteratedInTheConfiguredBatchSize(): void
    {
        $factory = new FakeIteratorFactory();

        (new RefundAdminSearchIndexer($this->connection, $factory, $this->repository, 100))->getIterator();

        static::assertSame([['definition' => RefundDefinition::ENTITY_NAME, 'limit' => 100]], $factory->getCreatedIterators());
    }

    public function testTheSearchResultIsResolvedIntoRefundEntities(): void
    {
        $refund = new RefundEntity();
        $refund->setId('refund-id');
        $this->repository->add($refund);

        $globalData = $this->buildIndexer()->globalData(
            ['total' => 1, 'hits' => [['id' => 'refund-id']]],
            Context::createDefaultContext()
        );

        static::assertSame(1, $globalData['total']);
        static::assertCount(1, $globalData['data']);
        static::assertSame('refund-id', $globalData['data']->first()?->getId());
    }

    private function buildIndexer(): RefundAdminSearchIndexer
    {
        return new RefundAdminSearchIndexer($this->connection, new FakeIteratorFactory(), $this->repository, 100);
    }
}
