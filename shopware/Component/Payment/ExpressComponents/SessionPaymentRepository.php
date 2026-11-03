<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final class SessionPaymentRepository implements SessionPaymentRepositoryInterface
{
    private const TABLE = 'mollie_session_payments';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $webhookId, string $sessionId): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => Uuid::fromHexToBytes($webhookId),
            'session_id' => $sessionId,
            'created_at' => $this->now(),
        ]);
    }

    public function findById(string $webhookId): ?SessionPayment
    {
        if (! Uuid::isValid($webhookId)) {
            return null;
        }

        /** @var array{session_id: string, payment_id: ?string, transaction_id: ?string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT session_id, payment_id, transaction_id FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        if ($row === false) {
            return null;
        }

        $transactionId = $row['transaction_id'] !== null ? Uuid::fromBytesToHex($row['transaction_id']) : null;

        return new SessionPayment($webhookId, $row['session_id'], $row['payment_id'], $transactionId);
    }

    public function assignPayment(string $webhookId, string $paymentId): void
    {
        $this->connection->update(
            self::TABLE,
            ['payment_id' => $paymentId, 'updated_at' => $this->now()],
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );
    }

    public function assignTransaction(string $sessionId, string $transactionId): bool
    {
        $affectedRows = $this->connection->update(
            self::TABLE,
            ['transaction_id' => Uuid::fromHexToBytes($transactionId), 'updated_at' => $this->now()],
            ['session_id' => $sessionId]
        );

        return $affectedRows > 0;
    }

    public function deleteByTransactionId(string $transactionId): void
    {
        $this->connection->delete(self::TABLE, ['transaction_id' => Uuid::fromHexToBytes($transactionId)]);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
