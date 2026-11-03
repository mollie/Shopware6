<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepository;
use Mollie\Shopware\Unit\Fake\FakeConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SessionPaymentRepository::class)]
final class SessionPaymentRepositoryTest extends TestCase
{
    private const WEBHOOK_ID = '01a1a1e69fa671f3a5042d40067b1597';
    private const TRANSACTION_ID = '0198f2c4e7a5730f9b1d2e3f4a5b6c7d';

    public function testCreatedEntryIsStoredUnderTheWebhookId(): void
    {
        $connection = new FakeConnection();

        (new SessionPaymentRepository($connection))->create(self::WEBHOOK_ID, 'ses_1');

        $this->assertStringStartsWith('INSERT INTO mollie_session_payments', $connection->getExecutedStatements()[0]);
        $this->assertSame([Uuid::fromHexToBytes(self::WEBHOOK_ID), 'ses_1'], array_slice($connection->getExecutedParameters()[0], 0, 2));
    }

    public function testStoredEntryIsReadWithHexIds(): void
    {
        $connection = new FakeConnection();
        $connection->withRows([['session_id' => 'ses_1', 'payment_id' => 'tr_1', 'transaction_id' => Uuid::fromHexToBytes(self::TRANSACTION_ID)]]);

        $entry = (new SessionPaymentRepository($connection))->findById(self::WEBHOOK_ID);

        $this->assertNotNull($entry);
        $this->assertSame(self::WEBHOOK_ID, $entry->getId());
        $this->assertSame('ses_1', $entry->getSessionId());
        $this->assertSame('tr_1', $entry->getPaymentId());
        $this->assertSame(self::TRANSACTION_ID, $entry->getTransactionId());
    }

    public function testEntryWithoutTransactionHasNoTransactionId(): void
    {
        $connection = new FakeConnection();
        $connection->withRows([['session_id' => 'ses_1', 'payment_id' => null, 'transaction_id' => null]]);

        $entry = (new SessionPaymentRepository($connection))->findById(self::WEBHOOK_ID);

        $this->assertNull($entry?->getTransactionId());
    }

    public function testUnknownWebhookIdFindsNothing(): void
    {
        $connection = new FakeConnection();

        $this->assertNull((new SessionPaymentRepository($connection))->findById(self::WEBHOOK_ID));
    }

    public function testInvalidWebhookIdIsNotLookedUp(): void
    {
        $connection = new FakeConnection();

        $entry = (new SessionPaymentRepository($connection))->findById('not-a-uuid');

        $this->assertNull($entry);
        $this->assertSame([], $connection->getFetchedStatements());
    }

    public function testAssigningATransactionReportsAMatchingSession(): void
    {
        $connection = new FakeConnection();
        $connection->withAffectedRows(1);

        $this->assertTrue((new SessionPaymentRepository($connection))->assignTransaction('ses_1', self::TRANSACTION_ID));
    }

    public function testAssigningATransactionReportsAMissingSession(): void
    {
        $connection = new FakeConnection();

        $this->assertFalse((new SessionPaymentRepository($connection))->assignTransaction('ses_unknown', self::TRANSACTION_ID));
    }

    public function testEntryIsDeletedByItsTransaction(): void
    {
        $connection = new FakeConnection();

        (new SessionPaymentRepository($connection))->deleteByTransactionId(self::TRANSACTION_ID);

        $this->assertStringStartsWith('DELETE FROM mollie_session_payments', $connection->getExecutedStatements()[0]);
        $this->assertSame([Uuid::fromHexToBytes(self::TRANSACTION_ID)], $connection->getExecutedParameters()[0]);
    }
}
