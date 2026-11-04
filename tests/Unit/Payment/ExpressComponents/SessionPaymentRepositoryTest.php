<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentDefinition;
use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentEntity;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepository;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

#[CoversClass(SessionPaymentRepository::class)]
final class SessionPaymentRepositoryTest extends TestCase
{
    private const WEBHOOK_ID = '01a1a1e69fa671f3a5042d40067b1597';
    private const TRANSACTION_ID = '0198f2c4e7a5730f9b1d2e3f4a5b6c7d';

    private FakeEntityRepository $entityRepository;
    private Context $context;

    protected function setUp(): void
    {
        $this->entityRepository = new FakeEntityRepository(new SessionPaymentDefinition());
        $this->context = Context::createDefaultContext();
    }

    public function testCreatedEntryIsStoredUnderTheWebhookId(): void
    {
        $this->expectWrite();

        $this->createRepository()->create(self::WEBHOOK_ID, 'ses_1', $this->context);

        $this->assertSame([['id' => self::WEBHOOK_ID, 'sessionId' => 'ses_1']], $this->entityRepository->data[0]);
    }

    public function testStoredEntryIsFoundByTheWebhookId(): void
    {
        $this->entityRepository->entitySearchResults[] = $this->searchResult(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1', 'tr_1'));

        $entry = $this->createRepository()->findById(self::WEBHOOK_ID, $this->context);

        $this->assertSame('tr_1', $entry?->getPaymentId());
        $this->assertSame([self::WEBHOOK_ID], $this->entityRepository->criteria[0]->getIds());
    }

    public function testInvalidWebhookIdIsNotLookedUp(): void
    {
        $entry = $this->createRepository()->findById('not-a-uuid', $this->context);

        $this->assertNull($entry);
        $this->assertSame([], $this->entityRepository->criteria);
    }

    public function testPaymentIdIsWrittenToTheEntry(): void
    {
        $this->expectWrite();

        $this->createRepository()->assignPayment(self::WEBHOOK_ID, 'tr_1', $this->context);

        $this->assertSame([['id' => self::WEBHOOK_ID, 'paymentId' => 'tr_1']], $this->entityRepository->data[0]);
    }

    public function testTransactionIsWrittenToTheEntryOfTheSession(): void
    {
        $this->entityRepository->entitySearchResults[] = $this->searchResult(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1'));
        $this->expectWrite();

        $this->createRepository()->assignTransaction('ses_1', self::TRANSACTION_ID, $this->context);

        $this->assertSame([['id' => self::WEBHOOK_ID, 'transactionId' => self::TRANSACTION_ID]], $this->entityRepository->data[0]);
    }

    public function testAssignedEntryCarriesTheStoredPaymentId(): void
    {
        $this->entityRepository->entitySearchResults[] = $this->searchResult(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1', 'tr_1'));
        $this->expectWrite();

        $entry = $this->createRepository()->assignTransaction('ses_1', self::TRANSACTION_ID, $this->context);

        $this->assertSame('tr_1', $entry?->getPaymentId());
        $this->assertSame(self::TRANSACTION_ID, $entry?->getTransactionId());
    }

    public function testTransactionOfAnUnknownSessionIsReported(): void
    {
        $this->entityRepository->entitySearchResults[] = new EntitySearchResult(SessionPaymentDefinition::ENTITY_NAME, 0, new SessionPaymentCollection(), null, new Criteria(), $this->context);

        $entry = $this->createRepository()->assignTransaction('ses_unknown', self::TRANSACTION_ID, $this->context);

        $this->assertNull($entry);
        $this->assertSame([], $this->entityRepository->data);
    }

    public function testEntryOfTheTransactionIsDeleted(): void
    {
        $this->entityRepository->idSearchResults[] = $this->idResult(self::WEBHOOK_ID);
        $this->expectWrite();

        $this->createRepository()->deleteByTransactionId(self::TRANSACTION_ID, $this->context);

        $this->assertSame([['id' => self::WEBHOOK_ID]], $this->entityRepository->data[0]);
    }

    public function testExpiredEntriesAreDeletedInOneBatch(): void
    {
        $this->entityRepository->idSearchResults[] = $this->idResult(self::WEBHOOK_ID, self::TRANSACTION_ID);
        $this->expectWrite();

        $deleted = $this->createRepository()->deleteExpired(new \DateTimeImmutable('2026-11-04 12:00:00'), $this->context);

        $this->assertSame(2, $deleted);
        $this->assertSame([['id' => self::WEBHOOK_ID], ['id' => self::TRANSACTION_ID]], $this->entityRepository->data[0]);
    }

    public function testNothingIsDeletedWithoutExpiredEntries(): void
    {
        $this->entityRepository->idSearchResults[] = $this->idResult();

        $deleted = $this->createRepository()->deleteExpired(new \DateTimeImmutable('2026-11-04 12:00:00'), $this->context);

        $this->assertSame(0, $deleted);
        $this->assertSame([], $this->entityRepository->data);
    }

    private function createRepository(): SessionPaymentRepository
    {
        return new SessionPaymentRepository($this->entityRepository);
    }

    private function expectWrite(): void
    {
        $this->entityRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent($this->context, new NestedEventCollection(), []);
    }

    private function idResult(string ...$ids): IdSearchResult
    {
        $data = array_map(static fn (string $id): array => ['primaryKey' => $id, 'data' => ['id' => $id]], $ids);

        return new IdSearchResult(count($data), $data, new Criteria(), $this->context);
    }

    private function searchResult(SessionPaymentEntity $entry): EntitySearchResult
    {
        return new EntitySearchResult(SessionPaymentDefinition::ENTITY_NAME, 1, new SessionPaymentCollection([$entry]), null, new Criteria(), $this->context);
    }
}
